<?php
	/*
		Signpost publishing script (c) Harry Burt <http://harryburt.co.uk>

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

	session_start();
	require_once('/data/project/jarry-common/public_html/peachy/Init.php');
	require_once('/data/project/jarry-common/public_html/global.php');
	
	error_reporting( E_ALL );
	ini_set( 'display_errors', "true" );

	$wiki = Peachy::newWiki('livingbot');
	echo get_html( 'header', '<i>Signpost</i> publication' );
	
	$ontime = true;
	if ( date( 'l' ) == 'Wednesday' ){
		$publicationdatestamp = time();
	} else {
		$publicationdatestamp = strtotime( 'last Wednesday' );
		if ( date( 'l' ) != 'Thursday' ){
			$ontime = false;
		}
	}
	
	$week = 60*60*24*7;
	$thisissue = date( 'Y-m-d', $publicationdatestamp );
	$lastissue = date( 'Y-m-d', $publicationdatestamp - $week );
	$nextissue = date( 'Y-m-d', $publicationdatestamp + $week );
	$fortnightissue = date( 'Y-m-d', $publicationdatestamp + 2 * $week );
	$volumenumber = intval( date( 'Y', $publicationdatestamp ) ) - 2004;
	$issuenumber = date( 'W', $publicationdatestamp );
	$yearstartsona  = date( 'l', strtotime( date( '1 \J\a\n Y' ) ) );
	if( $yearstartsona == 'Thursday' ) {
		$issuenumber--;
	}
	$issuenumber--; // 2013 hack
	$dmy = date( 'd F Y', $publicationdatestamp );
	$editor = "[[User:The_ed17|]]";
	ini_set( 'user_agent', 'Signpost delivery system' );
	$http = HTTP::getDefaultInstance();

	list( $emailpass, $identicapass, $blogpass )
		= explode( ';', file_get_contents( '/data/project/signpost/credentials.txt' ) );

	if( !isset( $_GET['step'] ) && !isset( $_POST['step'] ) ){
		$step = 1;
	} else {
		$step = isset( $_POST['step'] ) ? intval( $_POST['step'] ) : intval( $_GET['step'] );
	}

	if( !is_confirmed() && !is_debug() ){
		$nextstep = $step;
		$step = 0;
	} else {
		$nextstep = $step + 1;
	}

	if( $step == 0 ){
		echo "<p>Welcome. To continue, you need to supply a password below or enter 'dry run' mode by appending ?debug=true to the present URL. By pressing the button below you confirm that you wish to start the step named (for example, starting step one is responsible for officially 'publishing' <i>The Signpost</i>).</p>
		<form action='publish.php' method='POST'>
		<input type='hidden' name='step' id='step' value='$nextstep' />
		<input type='hidden' name='debug' id='debug' value='" . ( is_debug() ? "true" : "" ) . "' /> ";
		echo "\nPassword: <input type='password' name='confirm' id='confirm' />
		<input type='submit' /></form>.";
	}
	if( $step == 1 || $step == 2 ){
		$contents = $wiki->initPage( "Wikipedia:Wikipedia_Signpost/$thisissue" );
		$text = $contents->get_text();
		$subpages = $wiki->allpages( 4, "Wikipedia_Signpost/$thisissue/", null, 'nonredirects' );
		foreach( $subpages as &$subpage ){
			$subpage = $subpage['title'];
		}
		preg_match_all( '/\{\{[^}]+\{\{\{1\}\}\}[^}]+\}\}/', $text, $items );
		$items = $items[0];
		$stories = array();
		$count = 0;
		foreach( $items as $item ){
			$item = substr( $item, 2, -2 );
			$bits = explode( '|', $item );
			$stories[] = array(
				'name' => $bits[4],
			);
		}
		$i = 0;
		while( $i < count( $stories ) ){
			$story = &$stories[$i];
			if( !in_array( "Wikipedia:Wikipedia Signpost/$thisissue/" . $story['name'], $subpages ) ){
				array_splice( $stories, $i, 1 );
			} else {
				$pagename = "Wikipedia:Wikipedia Signpost/$thisissue/" . $story['name'];
				$details = parse_page( $pagename, $thisissue );
				$story = $story + $details;
				unset( $subpages[ array_search( $pagename, $subpages ) ] );
				$i++;
			}
		}
		$subpages = array_reverse( $subpages );
		foreach( $subpages as $subpage ){
			$newitem = parse_page( $subpage, $thisissue );
			array_unshift( $stories, $newitem );
		}
		$pagetext = '';
		$count = count( $stories );
		$leftcol = "";
		$rightcol = "";
		for( $i = 0; $i < $count; $i++ ){
			$coltext = "{{Wikipedia:Signpost/Template:Signpost-snippet|$thisissue|";
			$coltext .= $stories[$i]['name'] . '|';
			$coltext .= $stories[$i]['subtitle'] . '|4=';
			$coltext .= $stories[$i]['paragraph'] . '}}' . "\n\n";
			if( $i <= ( ( $count + 1 ) / 2 ) ){
				$leftcol .= $coltext;
			} else {
				$rightcol .= $coltext;
			}
		}
	}
	if( $step == 1 ){
		//Step 1a: main page
		$mainpagetext = "<noinclude>{{pp-semi-indef}}{{pp-move-indef}}</noinclude>
{{Wikipedia:Signpost/Template:Signpost-header|{{Str left|{{Wikipedia:Wikipedia Signpost/Issue|2}}|9}}|{{date|{{Wikipedia:Wikipedia Signpost/Issue|1}}|dmy}}|{{Str right|{{Wikipedia:Wikipedia Signpost/Issue|2}}|10}}}}
<div><!-- Main area -->
<div style=\"width:33%; float:left; margin:15px 0;\"><div style=\"margin-left:3em; margin-right:1.5em;\" class=\"plainlinks\">\n";
		$mainpagetext .= $leftcol;
		$mainpagetext .= "</div><div style=\"width:33%; float:left; margin:15px 0;\"><div style=\"margin-left:3em; margin-right:1.5em;\" class=\"plainlinks\"><!-- No time to code the bot so it puts something useful here for you, sorry -- Jarry1250 --></div>
<div style=\"width:33%; float:left; margin:15px 0;\"><div style=\"margin-right:3em; margin-left:1.5em;\">\n";
		$mainpagetext .= $rightcol;
		$mainpagetext .= "</div></div>

</div>
</div>
<div style=\"clear:both; font-family:Georgia, Palatino, Palatino Linotype, Times, Times New Roman, serif; text-align:center; font-size:100%; line-height:120%; margin-bottom:-42px; margin-top:30px;\">'''[[Wikipedia:Wikipedia Signpost/Single|Single-page edition]]{{#ifexist: Book:Wikipedia Signpost/{{Wikipedia:Wikipedia Signpost/Issue|1}} |  {{·}} [[Book:Wikipedia Signpost/{{Wikipedia:Wikipedia Signpost/Issue|1}}|Book edition]] |  }}'''</div>
{{Wikipedia:Signpost/Template:Signpost-footer|{{Wikipedia:Wikipedia Signpost/Issue|3}}|{{Wikipedia:Wikipedia Signpost/Issue|4}}}}<noinclude>
[[Category:Wikipedia Signpost]]
</noinclude>";
		do_edit( "Wikipedia:Wikipedia_Signpost", $mainpagetext, "(on behalf of $editor) bot creating basic main page ready for manual editing" );
		echo "<form action='publish.php' method='POST'>
		<input type='hidden' name='step' id='step' value='$nextstep' />
		<input type='submit' value='Continue to step #$nextstep of 4' /></form>.";
	}
	if( $step == 2 ){
		// Step 1b: main page
		for( $i = 0; $i < $count; $i++ ){
			$pagetext .= '{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|';
			$pagetext .= ($i + 1);
			$pagetext .= '|' . $thisissue . '|';
			$pagetext .= $stories[$i]['name'] . '|';
			$pagetext .= $stories[$i]['subtitle'] . '}}' . "\n";
		}
		$pagetext = trim( $pagetext );
		do_edit( "Wikipedia:Wikipedia_Signpost/$thisissue", $pagetext, "(on behalf of $editor) bot updating articles for inclusion, subtitles" );

		//Step 2-4: not run

		//Step 5: Issue page
		$page = $wiki->initPage( 'Wikipedia:Wikipedia_Signpost/Issue' );
		$text = $page->get_text();
		$text = explode('<noinclude>', $text);
		$pagetext = "{{<includeonly>safesubst:</includeonly>#switch: {{{1}}}
	| 1 = $thisissue
	| 2 = Volume $volumenumber, Issue $issuenumber
	| 3 = $lastissue
	| 4 = $nextissue
	| 5 = $fortnightissue
	}}<noinclude>" . $text[1];
		$summary = "(on behalf of $editor) bot publishing Signpost Volume $volumenumber, Issue $issuenumber for ";
		$summary .= $dmy . ".";
		if( !$ontime ) $summary .= ' Sorry for the delay!';
		do_edit( 'Wikipedia:Wikipedia_Signpost/Issue', $pagetext, $summary, false, false );

		//Step 6: purges
		$wiki->purge( array( 'Wikipedia:Wikipedia Signpost/Issue', 'Wikipedia:Wikipedia Signpost', 'Wikipedia:Signpost/Single', 'Wikipedia:Wikipedia_Signpost/Newsroom/Publishing', 'Wikipedia:Wikipedia_Signpost/Archives/2011-07-25' ) );

		echo "<form action='publish.php' method='POST'>
		<input type='hidden' name='step' id='step' value='$nextstep' />
		<input type='submit' value='Continue to step #$nextstep of 4' /></form>.";
	}
	if( $step == 3 ){
		$wiki->purge( array( 'Wikipedia:Wikipedia Signpost/Issue', 'Wikipedia:Wikipedia Signpost', 'Wikipedia:Signpost/Single', 'Wikipedia:Wikipedia_Signpost/Newsroom/Publishing', 'Wikipedia:Wikipedia_Signpost/Archives/2011-07-25' ) );

		//Step 7: mailing lists
		$message = file_get_contents( "http://en.wikipedia.org/w/api.php?action=expandtemplates&format=json&text={{Wikipedia:Wikipedia%20Signpost/{{Wikipedia:Wikipedia%20Signpost/Issue|1}}|7}}" );
		$message = json_decode( $message, true );
		$message = $message['expandtemplates']['*'];
		$message = str_replace( "<br />", "\n", str_replace( "\n", '', $message ) );
		$message .= "\nSingle page view\nhttp://en.wikipedia.org/wiki/Wikipedia:Signpost/Single\n\nPDF version\nhttp://en.wikipedia.org/wiki/Book:Wikipedia_Signpost/$thisissue";
		$message .= "\n\n\nhttps://www.facebook.com/wikisignpost / https://twitter.com/wikisignpost\n--\nWikipedia Signpost Staff\nhttp://en.wikipedia.org/wiki/Wikipedia:Wikipedia_Signpost";
		$subject = "The Signpost -- Volume $volumenumber, Issue $issuenumber -- " . $dmy;
		$to = "wikimediaannounce-l <WikimediaAnnounce-l@lists.wikimedia.org>";
		$headers = 'From: Wikipedia Signpost <wikipediasignpost@gmail.com>' . "\r\n";
		if( is_confirmed() && !is_debug() ){
			mail( $to, $subject, $message, $headers );
			echo "Emailed foundation-l (via autoforward), WikimediaAnnounce-l. Note that this process takes some time, as the message must be approved by a moderator of the announce list.<br />";
		} else{
			echo "<strong>Would have emailed:</strong><br />$message<br /><br />";
		}

		echo "<form action='publish.php' method='POST'>
		<input type='hidden' name='step' id='step' value='$nextstep' />
		<input type='submit' value='Continue to step #$nextstep of 4' /></form>.";
	}
	if( $step == 4 ){
		$wiki->purge( array( 'Wikipedia:Wikipedia Signpost/Issue', 'Wikipedia:Wikipedia Signpost', 'Wikipedia:Signpost/Single', 'Wikipedia:Wikipedia_Signpost/Newsroom/Publishing', 'Wikipedia:Wikipedia_Signpost/Archives/2011-07-25' ) );

		//Step 8: local delivery
		$message = "<div lang=\"en\" dir=\"ltr\" class=\"mw-content-ltr\"><div style=\"-moz-column-count:2; -webkit-column-count:2; column-count:2;\">\n{{Wikipedia:Wikipedia Signpost/{{subst:Wikipedia:Wikipedia_Signpost/Issue|1}}}}\n</div><!--Volume $volumenumber, Issue $issuenumber-->\n";
		$message .= "<div class=\"hlist\" style=\"margin-top:10px; font-size:90%; padding-left:5px; font-family:Georgia, Palatino, Palatino Linotype, Times, Times New Roman, serif;\">\n* '''[[Wikipedia:Wikipedia Signpost|Read this Signpost in full]]'''\n* [[Wikipedia:Signpost/Single|Single-page]]\n* [[Wikipedia:Wikipedia Signpost/Subscribe|Unsubscribe]]\n* ~~~~\n</div></div>";
		$tokens = $wiki->get_tokens();
		$result = $http->post( $wiki->get_base_url(),
			array(
				'action' => 'massmessage',
				'spamlist' => 'User:Jarry1250/spamlist',
				'subject' => "''(test) The Signpost'': " . $dmy,
				'message' => $message,
				'token' => $tokens['edit'],
				'format' => 'json'
			)
		);
		$result = json_decode( $result, true );
		echo "Publishing to the English Wikipedia... ";
		echo ( isset( $result['massmessage']['result'] ) && $result['massmessage']['result'] == 'success' )
			? 'Successful' : 'Failed';
		echo '<br />';

		//Step 9: identi.ca
		//[deprecated]

		//Step 10: blog
		//[deprecated]

		//Step 11: global subscribers
		$meta = Peachy::newWiki( 'livingbotmeta' );
		$tokens = $meta->get_tokens();

		$message = '<div lang=\"en\" dir=\"ltr\" class=\"mw-content-ltr\">';
		$message .= '<div style="margin-top:10px; font-size:90%; padding-left:5px; font-family:Georgia, Palatino, Palatino Linotype, Times, Times New Roman, serif;">';
		$message .= "''News, reports and features from the English Wikipedia's weekly journal about Wikipedia and Wikimedia''</div>\n";
		$message .= '<div style="-moz-column-count:2; -webkit-column-count:2; column-count:2;">' . "\n";
		$newbodytext = $http->get( $wiki->get_base_url() . "?action=expandtemplates&format=json&text={{Wikipedia:Wikipedia%20Signpost/{{Wikipedia:Wikipedia%20Signpost/Issue|1}}|8}}" );
		$newbodytext = json_decode( $newbodytext, true );
		$newbodytext = str_replace( array('<nowiki>', '</nowiki>'), '', $newbodytext['expandtemplates']['*'] );
		$newbodytext = str_replace( '<br /><br />', "", $newbodytext );
		$message .= $newbodytext . "</div>\n<div style=\"margin-top:10px; font-size:90%; padding-left:5px; font-family:Georgia, Palatino, Palatino Linotype, Times, Times New Roman, serif;\">'''[[w:en:Wikipedia:Wikipedia Signpost|Read this Signpost in full]]''' &middot; [[w:en:Wikipedia:Signpost/Single|Single-page]] &middot; [[m:Global message delivery/Targets/Signpost|Unsubscribe]] &middot; [[m:Global message delivery|Global message delivery]] ~~~~~\n</div>\n\n</source>";
		$result = $http->post( $meta->get_base_url(),
			array(
				'action' => 'massmessage',
				'spamlist' => 'Global message delivery/Targets/Signpost',
				'subject' => "''The Signpost'': " . $dmy,
				'message' => $message,
				'token' => $tokens['edit'],
				'format' => 'json'
			)
		);
		$result = json_decode( $result, true );
		echo "Publishing to the global wikis... ";
		echo ( isset( $result['massmessage']['result'] ) && $result['massmessage']['result'] == 'success' )
			? 'Successful' : 'Failed';
		echo '<br />';

		//Step 12: article alerts
		$page = $wiki->initPage( 'Wikipedia:Article_alerts/News' );
		$text = $page->get_text();
		$newtext = str_replace( '.<noinclude>', '.', $text );
		$lines = explode( "\n", $newtext );
		if( $text == $newtext || strpos( $newtext, "Signpost]]'' is out.") === false ){
			echo "Problem with Article Alert update, skipping...";
		} else {
			$newtext = array_shift( $lines ) . "\n";
			$newtext .= "* $dmy — A [[Wikipedia:Wikipedia Signpost/Archives/$thisissue|new edition]] of the ''[[WP:Signpost|Signpost]]'' is out.<noinclude>\n";
			$newtext .= implode( "\n", $lines );
			do_edit( 'Wikipedia:Article_alerts/News', $newtext, "(on behalf of $editor) bot updating to add new edition of ''[[WP:SIGNPOST|The Signpost]]''" );
		}

		//Step 13: book
		$newtext = "{{saved book\n |title=Wikipedia Signpost\n |subtitle= $dmy\n |cover-image=WikipediaSignpostIcon.svg\n |cover-color=White\n}}\n\n";
		$newtext .= "==The Wikipedia Signpost==\n=== $dmy ===\n";
		$body = file_get_contents( "http://en.wikipedia.org/w/api.php?action=expandtemplates&format=json&text={{Wikipedia:Wikipedia%20Signpost/{{Wikipedia:Wikipedia%20Signpost/Issue|1}}|5}}" );
		$body = json_decode( $body, true );
		$body = $body['expandtemplates']['*'];
		$newtext .= $body;
		$newtext .= "\n\n[[Category:Wikipedia books on the Wikipedia Signpost]]";
		do_edit( "Book:Wikipedia Signpost/$thisissue", $newtext, "(on behalf of $editor) bot creating as new edition of ''[[WP:SIGNPOST|The Signpost]]''" );
		
		//Step 14: Initiate the issue contents page Wikipedia:Wikipedia Signpost/YYYY-MM-DD
		$newtext = "{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|1|$nextissue|Special story 1|Write your 1st story here!}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|2|$nextissue|Special story 2|Write your 2nd story here!}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|3|$nextissue|Special story 3|Write your 3rd story here!}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|4|$nextissue|News and notes|Add to \"News and notes\"}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|5|$nextissue|In the media|Add to \"In the media\"}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|6|$nextissue|Recent research|Recent research}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|7|$nextissue|Discussion report|Discussion Reports and Miscellaneous Articulations}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|8|$nextissue|WikiProject report|Talking with WikiProject ????}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|9|$nextissue|Featured content|The best of the week}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|10|$nextissue|Opinion essay|Opinion essay}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|11|$nextissue|Arbitration report|The Report on Lengthy Litigation}}
		{{Wikipedia:Signpost/Template:Cover-item|{{{1}}}|12|$nextissue|Technology report|Bugs, Repairs, and Internal Operational News}}";
		$newtext = str_replace( "\t", "", $newtext );
		do_edit( "Wikipedia:Wikipedia Signpost/$nextissue", $newtext, "(on behalf of $editor) bot creating as new edition of ''[[WP:SIGNPOST|The Signpost]]''" );
			
		//Step 15: new archive page
		do_edit( "Wikipedia:Wikipedia Signpost/Archives/$nextissue", "{{Signpost archive|$thisissue|$nextissue|$fortnightissue}}", "(on behalf of $editor) bot creating archives of new edition of ''[[WP:SIGNPOST|The Signpost]]''" );

		//Step 16: tell Yuvi's app there's a new issue
		$http->post( 'tools-webproxy.pmtpa.wmflabs/wp-signpost/issue/update/latest', array() );
		
		//Step 17: permanent Single pages
		$newtext = "{{Wikipedia:Wikipedia Signpost/Single|issuedate=$thisissue}}";
		$pagename = "Wikipedia:Wikipedia Signpost/Single/$thisissue";
		do_edit( $pagename, $newtext, "(on behalf of $editor) bot creating permanent single page edition" );

		//Step 18: More archives
		$pagename = 'Wikipedia:Wikipedia_Signpost/Archives/' . date( 'Y', $publicationdatestamp );
		$header = '== ' . date( 'F', $publicationdatestamp ) . ' ==';
		$page = $wiki->initPage( $pagename );
		$text = $page->get_text();
		if( strpos( $text, "Wikipedia:Wikipedia Signpost/Archives/$thisissue" ) === false ){
			$newentry = "===[[Wikipedia:Wikipedia Signpost/Archives/$thisissue|Volume $volumenumber, Issue $issuenumber]], $dmy===
{{Wikipedia:Wikipedia Signpost/$thisissue}}\n\n";
			$footer = "</div>
{{Wikipedia:Signpost/Template:Signpost-footer}}
[[Category:Wikipedia Signpost archives]]";
			list( $text ) = explode( "</div>", $text );
			if( strpos( $text, $header ) === false ){
				$text .= $header . "\n";
			}
			$text .= $newentry . $footer;
			do_edit( $pagename, $text, "(on behalf of $editor) bot adding latest issue to archive" );
		}

		echo "All done.";
	}

	echo get_html( 'footer' );
	
	function parse_page( $pagename, $thisissue ){
		global $wiki;
		$item = array( 'name' => str_replace( "Wikipedia:Wikipedia Signpost/$thisissue/", '', $pagename ) );
		$page = $wiki->initPage( $pagename );
		$text = $page->get_text();
		preg_match( '/Signpost-article-start\| *(\{\{\{1\|)?([^|}]+)(\}\}\})? *\|/', $text, $matches );
		$item['subtitle'] = trim( $matches[2] );

		// Right, easy stuff out of the way, now to work out "lead paragraph"
		// mostly by stripping stuff.
		// Standardise header spacing
		$text = str_replace( "=\n\n", "=\n", $text );
		$text = str_replace( "=\n", "=\n\n", $text );
		$text = str_replace( "\n\n=", "\n=", $text );
		$text = str_replace( "\n=", "\n\n=", $text );

		$text = str_replace( "\n", "NEWLINE", $text );
		// Strip {{{1}}}, {{Foo}}, <noinclude>...</noinclude> etc.
		while( preg_match( '/\{\{\{[^{]*\}\}\}/', $text ) ){
			$text = preg_replace( '/\{\{\{[^{]*\}\}\}/', '', $text );
		}
		while( preg_match( '/\{\{[^{]*?\}\}/', $text ) ){
			$text = preg_replace( '/\{\{[^{]*?\}\}/', '', $text );
		}
		while( preg_match( '/[<][a-z]+[>][^<]*[<]\/[a-z]+[>]/', $text ) ){
			$text = preg_replace( '/[<][a-z]+ ?[^>]*[>][^<]*[<]\/[a-z]+[>]/', '', $text );
		}
		$text = str_replace( "NEWLINE", "\n", $text );

		// Strip links (internal + external) except images
		$text = str_replace( 'File:', '~', $text );
		while( preg_match( '/\[\[([^~][^|[]*)\]\]/', $text ) ){
			$text = preg_replace( '/\[\[([^~][^|[]*)\]\]/', '$1', $text );
		}
		while( preg_match( '/\[\[([^~][^[]*)\]\]/', $text ) ){
			$text = preg_replace( '/\[\[[^~][^[]*\|([^[]*)\]\]/', '$1', $text );
		}
		while( preg_match( '/\[http[^ ]+ ([^]]+)\]/', $text ) ){
			$text = preg_replace( '/\[http[^ ]+ ([^]]+)\]/', '$1', $text );
		}
		preg_match_all( '/\[\[~[^]]+\]\]/', $text, $images );
		$text = preg_replace( '/\[\[~[^]]+\]\]/', '', $text );

		// Now, find lead paragraph in the rubble and use it
		$text = str_replace( "\n\n\n", "\n\n", $text );
		$text = trim( $text );
		$paras = explode( "\n\n", $text );
		$paraToUse = "(No description.)";
		foreach( $paras as $para ){
			if( !preg_match( '/^=.*=$/', $para ) ) {
				$paraToUse = $para;
				break;
			}
		}
		$item['paragraph'] = trim( $paraToUse, " :'" );
		return $item;
	}
	
	function do_edit( $pagename, $newtext, $summary, $usemeta = false, $usebot = true ){
		global $wiki, $meta;
		if( $usemeta ){
			$page = $meta->initPage( $pagename );
		} else {
			$page = $wiki->initPage( $pagename );
		}
		$debug = is_debug();
		if( $debug ){
			// $oldtext = $page->get_text();
			echo "<strong>Would have edited page $pagename:</strong><br /><pre>";
			// require_once('/home/jarry/public_html/scripts/simplediff.php');
			// echo trim( str_replace( "<del></del>", "", htmlDiff( $oldtext, $newtext )));
			echo htmlspecialchars( $newtext ) . '</pre><br /><br />';
			return false;
		}

		$exists = $page->get_exists();
		if( !is_debug() ) echo "<!-- ";
		$status = $page->edit( $newtext, $summary, false, $usebot );
		if( !is_debug() ) echo " -->";
		if( $status ){
			echo "Edited $pagename ";
			if( $exists ){
				$history = $page->history( 2 );
				$rev = $history[1]['revid'];
				echo "(<a href='http://en.wikipedia.org/w/index.php?diff=cur&oldid=$rev'>diff</a>)";
			} else {
				echo "(<a href='http://en.wikipedia.org/w/index.php?title=$pagename'>page created</a>)";
			}
			echo ". Remember to review this change!<br />";

		} else{
			echo " <span style=\"color:red; font-weight:bold;\">Did not edit $pagename due to an error.";
			if( !is_debug() ) echo " Enter debug mode or view source to see what the problem was.";
			echo "<br />";
		}
		return $status;
	}
	
	function is_debug(){
		return ( ( isset( $_GET['debug'] ) && in_array( strtolower( $_GET['debug'] ), array( "y", "yes", "true", "1" ) ) ) || ( isset( $_POST['debug'] ) && in_array( strtolower( $_POST['debug'] ), array( "y", "yes", "true", "1" ) ) ) );
	}
	
	function is_confirmed(){
		global $emailpass;
		if( isset( $_POST['confirm'] ) ){
			$_SESSION['password'] = $_POST['confirm'];
		}
		return isset( $_SESSION['password'] ) && ( strtolower( $_SESSION['password'] ) == strtolower( $emailpass ) );
	}