<?php

namespace Omnireview\Harness;

use Omnireview\Registry;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The console: which gateways are installed and configured, what each
 * gives and on which terms - on the settings in .env, nothing called.
 * Asking a platform is bare's (bare --live).
 */
final class Console
{
    public static function create(): Application
    {
        $app = new Application('omnireview', '1.x');
        $command = new Command('gateways');
        $command->setDescription('Which gateways are installed and configured, what each gives, its terms');
        $command->setCode(static fn (InputInterface $in, OutputInterface $out): int => self::gateways($out));
        $app->addCommand($command);

        return $app;
    }

    private static function gateways(OutputInterface $out): int
    {
        $config = require __DIR__.'/../config/gateways.php';
        $factories = [];
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = new $class(HttpClient::create());
                $factories[$factory->getName()] = $factory;
            }
        }
        $missing = static fn (array $g) => array_values(array_filter($g['needs'], static fn (string $k) => false === getenv($k) || '' === getenv($k)));
        $registry = new Registry($factories, array_map(static fn (array $g) => ['factory' => $g['factory'], 'options' => $missing($g) ? $g['example'] : array_filter($g['options'], static fn ($v) => null !== $v)], array_filter($config, static fn (array $g) => isset($factories[$g['factory']]))));

        $table = new Table($out);
        $table->setHeaders(['Gateway', 'Installed', 'Settings', 'Gives', 'Kept', 'Reviews', 'Credited to']);
        foreach ($config as $name => $g) {
            if (!isset($factories[$g['factory']])) {
                $table->addRow([$name, '<comment>no</comment>']);
                continue;
            }
            $gateway = $registry->get($name);
            $t = $gateway->terms();
            $table->addRow([$name, '<info>yes</info>', ($m = $missing($g)) ? '<comment>example (needs '.implode(', ', $m).')</comment>' : '<info>.env</info>', implode(' ', $gateway->capabilities()->toArray()['gives']), null === $t->cacheFor ? 'per its policy' : (0 === $t->cacheFor ? 'never' : $t->cacheFor.' s'), $t->maxReviews ?? 'any', $t->attribution]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
