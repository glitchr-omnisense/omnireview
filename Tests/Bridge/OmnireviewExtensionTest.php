<?php

namespace Omnireview\Tests\Bridge;

use Omnireview\Bridge\Twig\OmnireviewExtension;
use Omnireview\Registry;
use Omnireview\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class OmnireviewExtensionTest extends TestCase
{
    private function twig(?int $cacheFor, ?Registry &$registry = null): Environment
    {
        $registry = new Registry([new StubFactory()], ['stub' => ['factory' => 'stub', 'options' => ['host' => 'reviews.example', 'cache_for' => $cacheFor]]]);
        $files = new FilesystemLoader();
        $files->addPath(__DIR__.'/../../Bridge/Twig/templates', 'Omnireview');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['page' => "{{ omnireview_widget('stub', 'p1') }}", 'twice' => "{{ omnireview_rating('stub', 'p1').value }}|{{ omnireview_rating('stub', 'p1').value }}", 'down' => "{{ omnireview_rating('stub', 'down') is null ? 'nothing' : 'something' }}", 'write-down' => "{{ omnireview_write_url('stub', 'down') is null ? 'nothing' : 'something' }}", 'widget-down' => "{{ omnireview_widget('stub', 'down') }}"]), $files]));
        $twig->addExtension(new OmnireviewExtension($registry, new ArrayAdapter(), 600));

        return $twig;
    }

    public function testTheWidgetShowsTheOriginTheDateTheLinkAndNoJsonLd(): void
    {
        $html = $this->twig(3600)->render('page');

        self::assertStringContainsString('<strong class="omnireview__score">8.6</strong><span class="omnireview__scale">/10</span>', $html);
        self::assertStringContainsString('<a class="omnireview__platform" href="https://reviews.example/p1" rel="noopener" target="_blank">Stub Reviews</a>', $html);
        self::assertStringContainsString('<a href="https://reviews.example/u/camille" rel="noopener nofollow" target="_blank">Camille R.</a>', $html);
        self::assertStringContainsString('<time datetime="2026-09-30T00:00:00+00:00">30/09/2026</time>', $html);
        self::assertStringContainsString('“Parfait &lt;b&gt;vraiment&lt;/b&gt;”', $html, 'the review\'s text escaped, within quotation marks');
        self::assertStringContainsString('href="https://reviews.example/r/1"', $html);
        self::assertStringContainsString('href="https://reviews.example/write/p1"', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
        self::assertStringNotContainsString('AggregateRating', $html);
    }

    public function testKeptNoLongerThanThePlatformAllows(): void
    {
        $this->twig(3600, $registry)->render('twice');
        self::assertSame(1, $registry->get('stub')->calls, 'kept: the platform allows an hour');

        $this->twig(0, $registry)->render('twice');
        self::assertSame(2, $registry->get('stub')->calls, 'never kept: the platform allows none');

        $this->twig(null, $registry)->render('twice');
        self::assertSame(2, $registry->get('stub')->calls, 'unknown: not kept');
    }

    public function testAPlatformThatDoesNotAnswerShowsNothing(): void
    {
        self::assertSame('nothing', $this->twig(0)->render('down'));
        self::assertSame('nothing', $this->twig(0)->render('write-down'), 'the write link fetched from a platform down: none, not an error');
        self::assertStringNotContainsString('omnireview__write', $this->twig(0)->render('widget-down'));
    }
}
