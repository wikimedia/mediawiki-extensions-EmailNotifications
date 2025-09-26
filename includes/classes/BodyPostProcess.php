<?php

/**
 * This file is part of the MediaWiki extension EmailNotifications.
 *
 * EmailNotifications is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * EmailNotifications is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EmailNotifications.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2024-2025, https://wikisphere.org
 */

namespace MediaWiki\Extension\EmailNotifications;

if ( is_readable( __DIR__ . '/../../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../../vendor/autoload.php';
}

class BodyPostProcess {

	/** @var string */
	private $baseHost;

	/** @var DOMDocument */
	private $htmlDom;

	/**
	 * @param string $baseHost
	 * @param string $html
	 */
	public function __construct( $baseHost, $html ) {
		$this->baseHost = $baseHost;

		libxml_use_internal_errors( true );
		$this->htmlDom = new \DOMDocument();
		$this->htmlDom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return $this->htmlDom->saveHTML();
	}

	public function updateImageUrls() {
		$imgElements = $this->htmlDom->getElementsByTagName( 'img' );

		foreach ( $imgElements as $img ) {
			$src = $img->getAttribute( 'src' );
			$resource = $img->getAttribute( 'resource' );
			$srcset = $img->getAttribute( 'srcset' );

			if ( $src ) {
				$img->setAttribute( 'src', $this->expandUrl( $src ) );
			}

			if ( $srcset ) {
				$srcsetUrls = explode( ', ', $srcset );
				$newSrcset = '';

				foreach ( $srcsetUrls as $srcsetUrl ) {
					$urlParts = explode( ' ', $srcsetUrl );
					$url = $this->expandUrl( $urlParts[0] );
					$newSrcset .= "$url {$urlParts[1]}";

					if ( isset( $urlParts[2] ) ) {
						$newSrcset .= ' ' . $urlParts[2];
					}

					$newSrcset .= ', ';
				}

				$newSrcset = rtrim( $newSrcset, ', ' );
				$img->setAttribute( 'srcset', $newSrcset );
			}
		}
	}

	/**
	 * @param string $resourceUrl
	 * @return string
	 */
	private function expandUrl( $resourceUrl ) {
		if ( !empty( $this->baseHost ) && strpos( $resourceUrl, $this->baseHost ) === false ) {
			return $this->baseHost . $resourceUrl;
		}
		return $resourceUrl;
	}
}
