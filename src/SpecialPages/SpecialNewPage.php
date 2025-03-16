<?php
namespace MediaWiki\Extension\NewPage\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;

final class SpecialNewPage extends SpecialNewPageBase {
	private SpecialPageFactory $specialPageFactory;
	private LinkRenderer $linkRenderer;
	private bool $hasSearchDigest;

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		SpecialPageFactory $specialPageFactory,
		LinkRenderer $linkRenderer,
	) {
		$this->hasSearchDigest = $extensionRegistry->isLoaded( 'SearchDigest' );
		$this->specialPageFactory = $specialPageFactory;
		$this->linkRenderer = $linkRenderer;
		parent::__construct( 'NewPage', 'edit' );
	}

	protected function getJsModuleName(): string {
		return 'ext.newpage';
	}

	private function getQuerypageSection(
		string $specialPageName,
		string $titleMsg,
		string $bodyMsg,
		int $limit,
	): string {
		$specialPage = $this->specialPageFactory->getPage( $specialPageName );
		$result = $specialPage->doQuery( 0, $limit );
		$links = [];
		foreach ( $result as $row ) {
			$title = Title::makeTitle( $row->namespace, $row->title );
			$links[] = Html::rawElement(
				'li',
				[],
				$this->linkRenderer->makeLink( $title )
			);
		}
		if ( empty( $links ) ) {
			return '';
		}
		return Html::element( 'h3', [], $this->msg( $titleMsg )->text() ) .
			Html::rawElement( 'p', [], $this->msg( $bodyMsg )->parse() ) .
			Html::rawElement( 'ol', [], implode( $links ) );
	}

	/**
	 * @return PanelLayout[]
	 */
	protected function getHelpRailModules(): array {
		$limit = $this->getConfig()->get( 'NewPageListLimit' );
		$wantedPagesSection = $this->getQuerypageSection(
			'Wantedpages',
			'wantedpages',
			'extnewpage-help-contributetext',
			$limit,
		);
		$searchDigestSection = $this->hasSearchDigest ? $this->getQuerypageSection(
			'SearchDigest',
			'searchdigest',
			'extnewpage-help-contributetext-searchdigest',
			$limit,
		) : false;
		return [
			new PanelLayout( [
				'classes' => [ 'extnewpage-rail-module' ],
				'expanded' => false,
				'padded' => false,
				'framed' => false,
				'content' => new HtmlSnippet( implode( ' ', [
					Html::rawElement( 'h2', [], $this->msg( 'extnewpage-help-contributeheading' ) ),
					$wantedPagesSection,
					$searchDigestSection,
				] ) ),
			] ),
			new PanelLayout( [
				'classes' => [ 'extnewpage-rail-module' ],
				'expanded' => false,
				'padded' => false,
				'framed' => false,
				'content' => new HtmlSnippet(
					Html::rawElement( 'h2', [], $this->msg( 'extnewpage-help-nsheading' ) )
					. Html::rawElement( 'p', [], $this->msg( 'extnewpage-help-nstext' )->parse() )
				),
			] ) ,
		];
	}

	protected function getFormFields() {
		return [
			'title' => [
				'class' => HtmlComplexTitleField::class,
				'creatable' => true,
				'required' => true,
            ]
		];
	}

    public function onSubmit( array $data ) {
		$title = Title::makeTitleSafe( $data['title']['ns'], $data['title']['text'] );
		$url = $title->getFullUrlForRedirect( [
			'action' => 'edit',
		] );
		$this->getOutput()->redirect( $url );
    }
}
