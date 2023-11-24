<?php

declare(strict_types=1);

namespace App\Template\StaticPageLayout;

use Distantmagic\Docs\Template\StaticPageLayout as BaseStaticPageLayout;
use Distantmagic\Resonance\Attribute\Singleton;
use Distantmagic\Resonance\Attribute\StaticPageLayout;
use Distantmagic\Resonance\SingletonCollection;
use Distantmagic\Resonance\StaticPage;
use Distantmagic\Resonance\StaticPageContentRenderer;
use Generator;

#[Singleton(collection: SingletonCollection::StaticPageLayout)]
#[StaticPageLayout('modernphp:page')]
readonly class Page extends BaseStaticPageLayout
{
    public function __construct(
        private StaticPageContentRenderer $staticPageContentRenderer,
    ) {}

    /**
     * @return Generator<string>
     */
    public function renderStaticPage(StaticPage $staticPage): Generator
    {
        yield <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
        </head>
        <body>
        HTML;
        yield from $this->renderBodyContent($staticPage);
        yield <<<'HTML'
        </body>
        </html>
        HTML;
    }

    /**
     * @return Generator<string>
     */
    protected function renderBodyContent(StaticPage $staticPage): Generator
    {
        yield $this->staticPageContentRenderer->renderContent($staticPage);
    }
}
