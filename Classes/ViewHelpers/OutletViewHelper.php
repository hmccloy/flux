<?php
declare(strict_types=1);
namespace FluidTYPO3\Flux\ViewHelpers;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * ViewHelper to configure outlets
 *
 * see `<flux:outlet.argument>` and `<flux:outlet.validate>` ViewHelpers for more information
 */
class OutletViewHelper extends AbstractFormViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('enabled', 'boolean', 'if the outlet is enabled', false, true);
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        $outlet = static::getFormFromRenderingContext($renderingContext)->getOutlet();
        $outlet->setEnabled((boolean)$arguments['enabled']);
        $renderChildrenClosure();
        return '';
    }
}
