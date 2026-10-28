<?php

namespace Spatie\LaravelAiTests\Viewer;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExtensionInterface;

class NestedHeadings implements ExtensionInterface
{
    public function __construct(
        protected int $parentLevel,
    ) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Heading) {
                    $node->setLevel(min($node->getLevel() + $this->parentLevel, 6));
                }
            }
        });
    }
}
