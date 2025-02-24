<?php
namespace FluidTYPO3\Flux\Tests\Unit\Content;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Content\ContentTypeFluxTemplateDumper;
use FluidTYPO3\Flux\Content\ContentTypeManager;
use FluidTYPO3\Flux\Content\TypeDefinition\ContentTypeDefinitionInterface;
use FluidTYPO3\Flux\Content\TypeDefinition\RecordBased\RecordBasedContentTypeDefinition;
use FluidTYPO3\Flux\Service\TemplateValidationService;
use FluidTYPO3\Flux\Tests\Unit\AbstractTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

class ContentTypeFluxTemplateDumperTest extends AbstractTestCase
{
    protected ?array $record = null;
    protected ?ContentTypeDefinitionInterface $contentTypeDefinition = null;
    protected ?ContentTypeManager $contentTypeManager = null;
    protected ?TemplateView $templateView;

    protected function setUp(): void
    {
        $this->contentTypeManager = new ContentTypeManager();

        $this->record = [
            'uid' => 123,
            'title' => 'Test form',
            'description' => 'Test form',
            'icon' => 'test',
            'content_type' => 'flux_test',
        ];
        $this->contentTypeDefinition = $this->getMockBuilder(RecordBasedContentTypeDefinition::class)
            ->setMethods(['getContentConfiguration', 'getGridConfiguration', 'getTemplateSource'])
            ->setConstructorArgs([$this->record])
            ->getMock();
        $this->contentTypeDefinition->method('getContentConfiguration')->willReturn([]);
        $this->contentTypeDefinition->method('getGridConfiguration')->willReturn([]);

        $this->contentTypeManager->registerTypeDefinition($this->contentTypeDefinition);

        $templateParser = new TemplateParser();

        $renderingContext = $this->getMockBuilder(RenderingContext::class)
            ->setMethods(
                [
                    'getViewHelperVariableContainer',
                    'getViewHelperResolver',
                    'getTemplateParser',
                    'getVariableProvider',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $renderingContext->method('getViewHelperVariableContainer')->willReturn(new ViewHelperVariableContainer());
        $renderingContext->method('getViewHelperResolver')->willReturn(new ViewHelperResolver());
        $renderingContext->method('getVariableProvider')->willReturn(new StandardVariableProvider());
        $renderingContext->method('getTemplateParser')->willReturn($templateParser);

        $templateParser->setRenderingContext($renderingContext);

        $this->templateView = new TemplateView($renderingContext);
        $this->templateView->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('flux', 'FluidTYPO3\\Flux\\ViewHelpers');


        $this->singletonInstances[ContentTypeManager::class] = $this->contentTypeManager;

        parent::setUp();
    }

    public function testDumpTemplateFromRecordReturnsEmptyStringOnMissingContentTypeDefinition(): void
    {
        $subject = $this->getMockBuilder(ContentTypeFluxTemplateDumper::class)
            ->setMethods(['getContentType'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('getContentType')->willReturn(null);

        self::assertSame('', $subject->dumpFluxTemplate(['row' => ['uid' => 123, 'content_type' => '']]));
    }

    public function testDumpTemplateFromRecordReturnsEmptyStringOnNewRecord(): void
    {
        $subject = new ContentTypeFluxTemplateDumper();

        self::assertSame('', $subject->dumpFluxTemplate(['row' => ['uid' => 'NEW123']]));
    }

    public function testDumpTemplateFromRecordBasedContentTypeDefinition(): void
    {
        GeneralUtility::addInstance(TemplateView::class, $this->templateView);

        $this->contentTypeDefinition->method('getTemplateSource')->willReturn('');

        $parameters = [
            'row' => $this->record,
        ];

        $subject = new ContentTypeFluxTemplateDumper();

        $output = $subject->dumpFluxTemplate($parameters);
        $expected = <<< SOURCE
<p class="text-success">Template parses OK, it is safe to copy</p><pre>&lt;f:layout /&gt;
&lt;f:section name=&quot;Configuration&quot;&gt;
    &lt;flux:form id=&quot;&quot;&gt;
        &lt;!-- Generated by EXT:flux from runtime configured content type --&gt;

    &lt;/flux:form&gt;
    &lt;flux:grid&gt;
        &lt;!-- Generated by EXT:flux from runtime configured content type --&gt;

    &lt;/flux:grid&gt;
&lt;/f:section&gt;

&lt;f:section name=&quot;Main&quot;&gt;

&lt;/f:section&gt;</pre>
SOURCE;

        self::assertSame($expected, $output);
    }

    public function testDumpTemplateRendersErrorIfTemplateParsingCausesError(): void
    {
        $this->contentTypeDefinition->method('getTemplateSource')->willReturn('<f:invalid');

        $parameters = [
            'row' => $this->record,
        ];

        $validationService = $this->getMockBuilder(TemplateValidationService::class)
            ->setMethods(['validateTemplateSource'])
            ->disableOriginalConstructor()
            ->getMock();
        $validationService->method('validateTemplateSource')->willReturn('test error');

        $subject = $this->getMockBuilder(ContentTypeFluxTemplateDumper::class)
            ->setMethods(['getTemplateValidationService'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('getTemplateValidationService')->willReturn($validationService);

        $output = $subject->dumpFluxTemplate($parameters);
        $expected = <<< SOURCE
<p class="text-danger">test error</p><pre>&lt;f:layout /&gt;
&lt;f:section name=&quot;Configuration&quot;&gt;
    &lt;flux:form id=&quot;&quot;&gt;
        &lt;!-- Generated by EXT:flux from runtime configured content type --&gt;

    &lt;/flux:form&gt;
    &lt;flux:grid&gt;
        &lt;!-- Generated by EXT:flux from runtime configured content type --&gt;

    &lt;/flux:grid&gt;
&lt;/f:section&gt;

&lt;f:section name=&quot;Main&quot;&gt;
&lt;f:invalid
&lt;/f:section&gt;</pre>
SOURCE;

        self::assertSame($expected, $output);
    }
}
