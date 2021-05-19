<?php

namespace MediaWiki\Extension\LinkCards;

use Html;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use Parser;
use Title;

class Hooks implements ParserFirstCallInitHook {

	/**
	 * @param Parser $parser Parser object being initialised
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'linkcard', [ $this, 'render' ] );
	}

	/**
	 * @param Parser $parser
	 * @return array|string
	 */
	public function render( Parser $parser ) {
		$args = $this->parseArgs( func_get_args() );

		// Title.
		$title = false;
		if ( $args['title'] ) {
			$title = Html::element( 'span', [ 'class' => 'ext-linkcards-title' ], $args['title'] );
		}

		// Link and main.
		$link = $args['link'];
		if ( !str_starts_with( $link, 'http' ) ) {
			$linkTitle = Title::newFromText( $link );
			if ( $linkTitle ) {
				$link = $linkTitle->getLocalURL();
			}
		}
		$anchorParams = [];
		if ( $link ) {
			$anchorParams = [ 'href' => $link ];
		}
		$main = Html::rawElement( 'span', [ 'class' => 'ext-linkcards-main' ], $title . ' ' . $args['body'] );
		$anchor = Html::rawElement( 'a', $anchorParams,  $this->getImageHtml( $args ) . ' ' . $main );

		// Main output.
		$parser->getOutput()->addModuleStyles( 'ext.LinkCards' );
		return [
			0 => Html::rawElement( 'div', [ 'class' => 'ext-linkcards-card' ], $anchor ),
			'isHTML' => true,
		];
	}

	/**
	 * @param string[] $args
	 * @return mixed[]
	 */
	private function parseArgs( $args ): array {
		$options = array_slice( $args, 1 );
		$link = isset( $options[0] ) && $options[0] !== '' ? $options[0] : '';
		unset( $options[0] );
		$argsSupplied = [];
		foreach ( $options as $option ) {
			$pair = array_map( 'trim', explode( '=', $option, 2 ) );
			if ( count( $pair ) === 2 ) {
				$argsSupplied[ $pair[0] ] = $pair[1];
			}
			if ( count( $pair ) === 1 ) {
				$argsSupplied[ $pair[0] ] = true;
			}
		}
		$argsDefaults = [
			'link' => $link,
			'title' => false,
			'body' => false,
			'image' => false,
			'image-width' => 300,
			'image-offset-dir' => false,
			'image-offset-val' => false,
		];
		return array_merge( $argsDefaults, $argsSupplied );
	}

	/**
	 * @param mixed[] $args
	 * @return string
	 */
	private function getImageHtml( $args ): string {
		if ( !$args['image'] ) {
			return '';
		}
		$imageTitle = Title::newFromText( 'File:' . $args[ 'image' ] );
		$file = MediaWikiServices::getInstance()
			->getRepoGroup()
			->findFile( $imageTitle );
		if ( !$file->exists() ) {
			return '';
		}
		$mediaTransformOutput = $file->transform( [ 'width' => $args['image-width'] ] );
		$style = '';
		if (
			in_array( $args['image-offset-dir'], [ 'top', 'left', 'bottom', 'right' ] )
			&& $args['image-offset-val']
		) {
			$offsetVal = filter_var(
				$args['image-offset-val'],
				FILTER_VALIDATE_INT,
				[ 'options' => [ 'min_range' => -100, 'max_range' => 100 ] ]
			);
			if ( $offsetVal ) {
				$style = $args['image-offset-dir'] . ': ' . $offsetVal . '%';
			}
		}
		$imgElement = Html::element( 'img', [
			'src' => $mediaTransformOutput->getUrl(),
			'width' => $mediaTransformOutput->getWidth(),
			'height' => $mediaTransformOutput->getHeight(),
			'style' => $style,
		] );
		$orientationClass = $mediaTransformOutput->getWidth() > $mediaTransformOutput->getHeight()
			? 'ext-linkcards-landscape'
			: 'ext-linkcards-portrait';
		$imageParams = [ 'class' => "ext-linkcards-image $orientationClass" ];
		return Html::rawElement( 'span', $imageParams, $imgElement );
	}
}
