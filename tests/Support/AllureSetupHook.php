<?php

declare(strict_types=1);

namespace app\tests\Support;

use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Bootstraps the Allure adapter for Codeception (codeception.yml → setupHook).
 *
 * Allure reads test docblocks with Doctrine Annotations and fails on tags it does not know.
 * Our tests use their own documentation tags, so they are registered as ignored here.
 */
final class AllureSetupHook
{
    /** Docblock tags of this project that are documentation, not Allure annotations */
    private const IGNORED_TAGS = ['flowStep'];

    /**
     * Called once by the Allure extension before the first test.
     *
     * @return void
     */
    public function __invoke(): void
    {
        foreach (self::IGNORED_TAGS as $tag) {
            AnnotationReader::addGlobalIgnoredName($tag);
        }
    }
}
