<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Topics extends \Consolidation\AnnotatedCommand\Attributes\Topics
{
}
