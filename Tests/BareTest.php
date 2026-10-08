<?php

namespace Omnireview\Tests;

use Omnireview\Bridge\Symfony\OmnireviewBundle;
use Omnireview\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnireview outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand and asks each gateway installed a place's
 * rating, reviews and link, the platforms answering from their recorded answers, then
 * reports every class and file PHP loaded on the way. None may be a
 * framework's; of Symfony, only the HTTP client.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Form|Validator|Routing)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine(?!\\\\Common\\\\Lexer)|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|form|validator|routing|[a-z-]*bundle|[a-z-]*bridge)|doctrine/(?!lexer/)[a-z-]+|twig)/~';

    /** @var array<string, mixed>|null */
    private static ?array $report = null;

    /** @return array<string, mixed> */
    private static function report(): array
    {
        if (null === self::$report) {
            [$status, self::$report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json']);
            self::assertSame(0, $status);
        }

        return self::$report;
    }

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        $report = self::report();

        self::assertFalse($report['live']);
        self::assertContains(Registry::class, $report['symbols'], 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $symfony = array_values(array_unique(array_map(static fn (string $s) => implode('\\', \array_slice(explode('\\', $s), 0, 3)), preg_grep('~^Symfony\\\\(?!Polyfill)~', $report['symbols']))));
        self::assertSame([], array_diff($symfony, ['Symfony\\Component\\HttpClient', 'Symfony\\Contracts\\HttpClient', 'Symfony\\Contracts\\Service']), 'of Symfony, libraries alone');
        $installed = array_values(array_filter(array_column(require __DIR__.'/../docker/harness/plugins.php', 1), 'class_exists'));
        foreach ($installed as $factory) {
            self::assertContains($factory, $report['symbols'], 'every gateway package installed, its factory built');
        }
        self::assertCount(\count($installed), $report['gateways']);
    }

    public function testEachPlatformGivesARatingReviewsAndTheirLinks(): void
    {
        $report = self::report();

        $expected = ['google' => [4.6, 5, 128], 'tripadvisor' => [4.5, 5, 312], 'trustpilot' => [4.4, 5, 469]];
        foreach ($expected as $name => $rating) {
            if (isset($report['runs'][$name])) {
                self::assertSame($rating, \array_slice($report['runs'][$name]['rating'], 0, 3), $name);
                self::assertNotNull($report['runs'][$name]['rating'][3], $name.': the rating links to its origin');
                self::assertNotEmpty($report['runs'][$name]['reviews'], $name);
                foreach ($report['runs'][$name]['reviews'] as [, , $date]) {
                    self::assertNotNull($date, $name.': every review is dated');
                }
                self::assertStringStartsWith('https://', $report['runs'][$name]['writeUrl'], $name);
            }
        }
        self::assertNotEmpty($report['runs'], 'at least one gateway package is installed beside this one');
        if (isset($report['gateways']['google'])) {
            self::assertSame(0, $report['gateways']['google']['terms']['cacheFor'], 'Google: nothing kept but the place ID');
        }
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNIREVIEW_AUTOLOAD"); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => get_included_files()]);', '--', OmnireviewBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        // Composer includes every installed package's "files" (installed for the
        // bridge's tests) before anything runs: loaded by the autoloader, not by Omnireview.
        $eager = require \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName()).'/autoload_files.php';
        $files = array_diff(array_map('realpath', $report['files']), array_map('realpath', array_values($eager)));

        return [...array_values(preg_grep(self::FRAMEWORK, $report['symbols'])), ...array_values(preg_grep(self::FRAMEWORK_FILES, $files))];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIREVIEW_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
