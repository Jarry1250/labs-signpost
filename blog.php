<?php
	/*
	Signpost blog importer (c) Harry Burt <http://harryburt.co.uk>

	Based on a python script by Resident Mario <https://en.wikipedia.org/wiki/User:Resident_Mario>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

	require_once('/data/project/jarry-common/public_html/global.php');

	$title = '<i>Signpost</i> blog importer';

	if( isset( $_GET['page'] ) ) {
		// Strip everything after the ? -- unnecessary and makes the regex much more difficult to write
		list( $page, ) = explode( '?', $_GET['page'] );
		if ( ! preg_match( '/^https:\/\/blog\.wikimedia\.org\/[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[a-zA-Z0-9-]+(\/)?$/', $page ) ) {
			error( 'URL format not recognised. This may be because you\'ve tried to use the tool on something that isn\'t the Wikimedia blog.', $title );
		}
	}

	echo get_html( 'header', $title );
?>
		<p>Hello. To get started, enter the URL of a Wikimedia blogpost into the form below. URLs should begin with <code>https://blog.wikimedia.org</code>.</p>
		<form method="GET">
			<p><label for="page">Blog URL:</label> <input name="page" id="page" type="text" required="required" placeholder="https://blog.wikimedia.org/2015/07/16/third-transparency-report-released/" style="width: 50em" value="<?php if( isset( $page ) ) echo $page; ?>"/></p>
			<input type="submit" />
		</form>

<?php
	if( isset( $_GET['page'] ) ) {
		// Strip everything after the ? -- unnecessary and makes the regex much more difficult to write
		list( $page, ) = explode( '?', $_GET['page'] );
		if ( ! preg_match( '/^https:\/\/blog\.wikimedia\.org\/[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[a-zA-Z0-9-]+(\/)?$/', $page ) ) {
			error( 'URL format not recognised. This may be because you\'ve tried to use the tool on something that isn\'t the Wikimedia blog.', $title );
		}

		// We don't need all of Peachy, just HTTP (which itself requires Hooks)
		require_once( '/data/project/jarry-common/public_html/peachy/Includes/Hooks.php' );
		require_once( '/data/project/jarry-common/public_html/peachy/HTTP.php' );

		$http = new HTTP( true );

		// Extract content
		$blog = $http->get( $page );
		preg_match( '/[<]article.*?[>](.*?)[<]\/article[>]/s', $blog, $html );
		$html = $html[1];

		// Tidy up (pre-conversion)
		$html = preg_replace( '/[<]p class="article-category".*?[>].*?[<]\/p[>]/s', '', $html );
		$html = preg_replace( '/[<]footer.*?[>].*?[<]\/footer[>]/s', '', $html );
		$html = preg_replace( '/[<]!--.*?--[>]/s', '', $html );

		$params = array(
			'html'           => $html,
			'scrub_wikitext' => true
		);
		$post   = $http->post( 'https://www.mediawiki.org/api/rest_v1/transform/html/to/wikitext', $params );
		if ( $post === false ) {
			die( 'ERROR: POST operation failed' . get_html('footer' ) );
		}

		// Tidy up (post-conversion)
		$post = preg_replace( '/[<][\/]?(p|div|small|header).*?[>]/', '', $post );
		$post = preg_replace( '/[\n][\t ]+/', "\n", $post );
		$post = preg_replace( '/[\r]/', '', $post );
		$post = preg_replace( '/[\n]{3,}/', "\n\n", $post );
		$post = str_replace( array( '[[:en:', '“', '”' ), array( '[[', '"', '"' ), $post );
		$post = trim( $post );

		$post = <<<OUTPUT
<noinclude>{{Signpost draft}}
{{Wikipedia:Signpost/Template:Signpost-header|||}}</noinclude>

<div style="padding-left:50px; padding-right:50px;">

{{Wikipedia:Signpost/Template:Signpost-article-start|{{{1|Your title}}}|By ?| {{subst:#time:j F Y|{{subst:Wikipedia:Wikipedia Signpost/Issue|4}}}}}}

</div>

{{Wikipedia:Wikipedia Signpost/Templates/WM Blog}}

<div style="width:46em; line-height:1.6em; font-size:1em; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; padding-left:5em;" class="plainlinks">$post</div>

<noinclude>{{Wikipedia:Signpost/Template:Signpost-article-comments-end||2015-04-22|2015-05-06}}</noinclude>
OUTPUT;
		echo "<h3>Output</h3>";
		echo "<pre>" . htmlspecialchars( $post ) . "</pre>";
	}
	echo get_html( 'footer' );