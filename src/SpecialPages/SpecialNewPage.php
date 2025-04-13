<?php
namespace MediaWiki\Extension\NewPage\SpecialPages;

use InvalidArgumentException;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\QueryPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;

final class SpecialNewPage extends SpecialNewPageBase {
	private readonly bool $hasSearchDigest;

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly LinkRenderer $linkRenderer
	) {
		parent::__construct( 'NewPage', 'edit' );
		$this->hasSearchDigest = $extensionRegistry->isLoaded( 'SearchDigest' );
	}

	protected function getJsModuleName(): string {
		return 'ext.newpage';
	}

	private function getQueryPageSection(
		string $specialPageName,
		string $titleMsg,
		string $bodyMsg,
		int $limit,
	): string {
		$specialPage = $this->specialPageFactory->getPage( $specialPageName );
		if ( !( $specialPage instanceof QueryPage ) ) {
			throw new InvalidArgumentException(
				"Cannot render a QueryPage section for a non-QueryPage special page: $specialPageName" );
		}

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

	private function getQueryPageRailModules(): array {
		$config = $this->getConfig();
		$limit = $config->get( 'NewPageListLimit' );

		$results = [];

		if ( $config->get( 'NewPageEnableWantedPagesModule' ) ) {
			$results[] = $this->getQueryPageSection(
				'Wantedpages',
				'wantedpages',
				'extnewpage-help-contributetext',
				$limit,
			);
		}

		if ( $this->hasSearchDigest && $config->get( 'NewPageEnableSearchDigestModule' ) ) {
			$results[] = $this->getQueryPageSection(
				'SearchDigest',
				'searchdigest',
				'extnewpage-help-contributetext-searchdigest',
				$limit,
			);
		}

		return $results;
	}

	/**
	 * @return PanelLayout[]
	 */
	protected function getHelpRailModules(): array {
		$results = [];

		$queryRailModules = $this->getQueryPageRailModules();
		if ( !empty( $queryRailModules ) ) {
			$results[] = new PanelLayout( [
				'classes' => [ 'extnewpage-rail-module' ],
				'expanded' => false,
				'padded' => false,
				'framed' => false,
				'content' => new HtmlSnippet( implode( ' ', [
					Html::rawElement( 'h2', [], $this->msg( 'extnewpage-help-contributeheading' ) ),
					...$queryRailModules,
				] ) ),
			] );
		}

		$results[] = new PanelLayout( [
			'classes' => [ 'extnewpage-rail-module' ],
			'expanded' => false,
			'padded' => false,
			'framed' => false,
			'content' => new HtmlSnippet(
				Html::rawElement( 'h2', [], $this->msg( 'extnewpage-help-nsheading' ) )
				. Html::rawElement( 'p', [], $this->msg( 'extnewpage-help-nstext' )->parse() )
			),
		] );

		return $results;
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
