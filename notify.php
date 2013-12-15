<?php
	/*
		Signpost notification script (c) Harry Burt <http://harryburt.co.uk>

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
	require_once('/data/project/jarry-common/peachy/Init.php');
	
	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
	$wiki = Peachy::newWiki('livingbot');

	if ( date( 'l' ) == 'Monday' ){
		$publicationdatestamp = time();
		$ontime = true;
	} else {
		die( 'This script should only be run on Mondays.' );
	}
	$week = 60*60*24*7;
	$thisissue = date( 'Y-m-d', $publicationdatestamp );
	$lastissue = date( 'Y-m-d', $publicationdatestamp - $week );
	$nextissue = date( 'Y-m-d', $publicationdatestamp + $week );
	$fortnightissue = date( 'Y-m-d', $publicationdatestamp + 2 * $week );
	$volumenumber = intval( date( 'Y', $publicationdatestamp ) ) - 2004;
	$issuenumber = date( 'W', $publicationdatestamp );
	$dmy = date( 'd F Y', $publicationdatestamp );
	$editor = "[[User:Jarry1250|]]";
	ini_set( 'user_agent', 'Signpost delivery system' );

	$subpages = $wiki->allpages( 4, "Wikipedia_Signpost/$thisissue/", null, 'nonredirects' );
	foreach( $subpages as &$subpage ){
		$subpage = $subpage['title'];
	}
	$tonotify = array();
	foreach( $subpages as $subpage ){
		if( strpos( $subpage, 'Arbitration' ) === false) continue;
		$page = initPage( $subpage );
		$text = $page->get_text();
		while( strpos( $text, '{{{' ) !== false ){
			$text = preg_replace( '/\{\{\{[^}]+\}\}\}/', '', $text );
		}
		while( strpos( $text, '{{' ) !== false ){
			$text = preg_replace( '/\{\{[^}]+\}\}/', '', $text );
		}
		preg_match_all( '/\[\[User:([^]|]+)(\||\])/', $text, $matches );
		$matches = $matches[1];
		$tonotify = array_merge( $tonotify, $matches );
	}
	$existing = file_get_contents( "/home/jarry/public_html/signpost/notify-record.txt" );
	$existing = explode( ';', $existing );
	if( array_shift( $existing ) != $thisissue ){
		$existing = array();
	}
	
	foreach( $tonotify as $user ){
		if( in_array( $user, $existing ) ) continue;
		do_edit_append( "User talk:$user", "\n\n{{subst:Wikipedia:Wikipedia_Signpost/Templates/Arb-notify|$thisissue}} ~~~~", "/* Friendly notification regarding this week's Signpost */ new section" );
		$existing[] = $user;
	}
	
	file_put_contents( "/data/project/signpost/notify-record.txt", "$thisissue;" . implode(';', $existing) );
	
	
	function do_edit_append( $pagename, $newtext, $summary ){
		$page = initPage( $pagename );
		$page->edit( $newtext, $summary, false, true, false, 'ap' );
	}
	