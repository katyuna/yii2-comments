<?php

declare(strict_types=1);

namespace app\tests\Support;

/**
 * Actor of the smoke suite: `$I` in smoke Cests.
 *
 * @SuppressWarnings(PHPMD)
*/
class SmokeTester extends \Codeception\Actor
{
    use _generated\SmokeTesterActions;
}
