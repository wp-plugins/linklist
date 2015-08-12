<?php
/*
Plugin Name: LinkList
Description: Adds a list of mentioned links at the end of the post, page or feed.
Plugin URI: http://wordpress.org/extend/plugins/linklist/
Version: 0.5
Requires at least: 3.5
Tested up to: 4.2
Stable tag: trunk
Author: Lutz Schr&ouml;er
Author URI: http://elektroelch.net/blog
*/


/*  This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
--------------------------------------------------------------------------- */

if ( !class_exists('LinkList') ) {
	class LinkList {
		var $content;
		var $linklist;
		var $prefix;

		/* ------------------------------------------------------------------------ */
		function linkExtractor($content){
			global $post;

            if ($this->options['exceptions']) {
                // remove divs
                $dom = new DOMDocument();
                $dom->loadHTML($content);
                $divs = $dom->getElementsByTagName('div');
                foreach ($divs as $div) {
                    if (in_array($div->getAttribute("class"), $this->options['exceptions']))
                        $div->parentNode->removeChild($div);
                } //foreach

                // saveHTML() is adding some additional tags to the doc. Remove them:
                $content = trim(preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>',
                                             '</body>'), array('', '', '', ''), $dom->saveHTML())));
            } //if

			$linkArray = array();
			if ( (preg_match_all('/<a\s+.*?href=[\"\']?([^\"\' >]*)[\"\']?[^>]*>(.*?)<\/a>/i',
						$content,$matches,PREG_SET_ORDER)))
				foreach($matches as $match) {
				if ( (strpos($match[0], '<img') <= 0) // avoid pure image links
					&& (strpos($match[0], '#more-'.$post->ID) <= 0)  // avoid <!--more--> links
					&& (! in_array(array($match[1],$match[2]), $linkArray))) // avoid double entries
						array_push($linkArray,array($match[1],$match[2]));
			} //if
		 return $linkArray;
		} //linkExtractor()
		/* ------------------------------------------------------------------------ */
		function LinkList($content) {
			$this->content = $content;
            $this->options = get_option('linklist');
		} //linklist()
		/* ------------------------------------------------------------------------ */
		function stopCreate() {
			return 0;
		}
		/* -------------------------------------------------------------------------- */
		function linklist_sorter($a, $b) {
			return strnatcasecmp( $a[1], $b[1] );
		}
		/* ------------------------------------------------------------------------ */
    	function createLinkList() {

		    // if the user has deslected to display the list only return the content
		    if (get_post_meta( get_the_ID(), 'linklist-display', true ) == '0')
			    return $this->content;

			if ($this->stopCreate())
			  return $this->content;

      		$this->linklist = $this->linkExtractor($this->content);
			if (! $this->linklist)
			  return $this->content;

			 // min number of links
			if (sizeof($this->linklist) < $this->options[$this->prefix . 'minlinks'] )
				return $this->content;

     		if ($this->options[$this->prefix . 'sort'])
				usort($this->linklist, array('LinkList', 'linklist_sorter'));

			$list = '<div class="linklist"><span class=linklistheader">' .
			$this->options[$this->prefix . 'prolog'] . '</span>';

			$del_start = "<li>";
			$del_end = "</li>";

			switch ($this->options[$this->prefix . 'style']) {
				case 'rbul': $start = "<ul>";
										 $end   = "</ul>";
										 break;
		        case 'rbol': $start = "<ol>";
					 					 $end   = "</ol>";
										 break;
				case 'rbli': $start = "";
										 $end   = "";
										 $del_start = "";
	  								 $del_end = $this->options[$this->prefix . 'sep'];
										 break;
		  } //switch

		  $list .= $start;
		  foreach ($this->linklist as $link)
		    $list .= $del_start . '<a href="' . $link[0] . '">' . $link[1].'</a>'.$del_end;

		  // remove last separator
		  if ($this->options[$this->prefix . 'style'] == "rbli")
		    $list = substr($list, 0, strlen($this->options[$this->prefix . 'sep']) * -1);

		  $list .= $end . "</div>";
		  $list = apply_filters('linklist', $list);
		  $this->content .= $list;

		  return $this->content;

		} //createLinkList()

	} //class LinkList
} //if

/* =========================================================================== */
if ( !class_exists('PageLinkList') ) {
	class PageLinkList extends LinkList{

		var $prefix;

		/* ------------------------------------------------------------------------ */
		function PageLinkList($content) {
			parent::LinkList($content);
			$this->prefix = 'page_';
		}
		/* ------------------------------------------------------------------------ */
		function stopCreate() {
			global $numpages, $page;

		if (! $this->options['page_active'])
			return 1;

		  if ($numpages > 1) //splitted page or post
			{
				// exit if display only on last page
				if ($this->options['page_last'] && ($numpages != $page))
					return 1;
			}

			return 0;  //default
		}
		/* ------------------------------------------------------------------------ */
		function linkExtractor($content) {
		  global $post;
			if ($this->options['page_last'])
			  return parent::linkExtractor($post->post_content);
      else
			  return parent::linkExtractor($this->content);
		}
	} //class PageLinkList
} //if

/* =========================================================================== */
if ( !class_exists('SingleLinkList') ) {
	class SingleLinkList extends LinkList{

		/* ------------------------------------------------------------------------ */
		function SingleLinkList($content) {
			parent::LinkList($content);
			$this->prefix = 'post_';
		}
	} //class SingleLinkList
} //if
/* =========================================================================== */
if ( !class_exists('FeedLinkList') ) {
	class FeedLinkList extends LinkList {
		/* ------------------------------------------------------------------------ */
		function stopCreate() {
			if (! $this->options['feed_active'])
				return 1;
			return 0;  //default
		}
		/* ------------------------------------------------------------------------ */
		function FeedLinkList($content) {
			parent::LinkList($content);
			$this->prefix = 'feed_';
		}
		/* ------------------------------------------------------------------------ */
  } //class FeedLinkList
}//if
/* =========================================================================== */
if ( !class_exists('BasicLinkList') ) {
	class BasicLinkList extends LinkList{

		/* -------------------------------------------------------------------------- */
		function hasMoreLink() {
			global $post;
			return strpos($post->post_content, '<!--more-->');
		}
 		/* ------------------------------------------------------------------------ */
		function stopCreate() {

			if (! $this->options['post_active'])
				return 1;

			if ($this->hasMoreLink())
		    if ($this->options[$this->prefix . 'more'])
					return 1;

			if ($this->options['post_display'])
			  return 1;

			return 0;
		}
 		/* ------------------------------------------------------------------------ */
		function BasicLinkList($content) {
			parent::LinkList($content);
			$this->prefix = 'post_';
		}
	} //class BasicLinkList
} //if
/* =========================================================================== */
function create_linklist($content) {
 global $options;

 if (is_page())
   $linklist = new PageLinkList($content);
 elseif (is_single())
   $linklist = new SingleLinkList($content);
 elseif (is_feed())
   $linklist = new FeedLinkList($content);
 else
   $linklist = new BasicLinkList($content);

return $linklist->createLinkList();

}  //create_linklist


/* --------------------------------------------------------------------------- */
function llactivate() {

	if (get_option('linklist'))
	  return;
	$options = ['post_active'   => 'on',
                'page_active'   => 'on',
                'feed_active'   => 'on',
                'post_prolog'   => 'Links in this post:',
                'page_prolog'   => 'Links on this page:',
                'feed_prolog'   => 'Links:',
                'post_style'    => 'rbol',
                'page_style'    => 'rbol',
                'feed_style'    => 'rbol',
                'post_display'  => '',
                'page_display'  => '',
                'post_more'     => 'on',
                'page_more'     => 'on',
                'post_minlinks' => 0,
                'page_minlinks' => 0,
                'feed_minlinks' => 0,
                'post_sep'      => ', ',
                'page_sep'      => ', ',
                'feed_sep'      => ', ',
                'post_sort'     => 'on',
                'page_sort'     => 'on',
                'feed_sort'     => 'on',
                'post_last'     => 'on',
                'page_last'     => 'on'
    ];
	update_option('linklist', $options);
}

/* --------------------------------------------------------------------------- */
function linklist_CreateMetaBoxContent($object) {


	wp_nonce_field(basename(__FILE__), "linklist-meta-box-nonce");

	$post_meta = get_post_meta($object->ID, "linklist-display", true);

	// if no post meta is available get the default value from the options

	if ($post_meta == '0')
		echo '<label for="linklist-display"><input id="linklist-display" name="linklist-display" type="checkbox" value="true">';
	else
		echo '<label for="linklist-display"><input id="linklist-display" name="linklist-display" type="checkbox" value="true"  checked="checked">';

	printf('&nbsp%s</label>', __('Display Linklist'));
}
/* --------------------------------------------------------------------------- */
function linklist_AddMetaBox() {

	$screens = array( 'post', 'page' );
	foreach ( $screens as $screen )
		add_meta_box('linklist-meta-box', 'Linklist', 'linklist_CreateMetaBoxContent', $screen, 'side', 'default', null);

}
/* --------------------------------------------------------------------------- */
function save_linklist_meta_box($post_id, $post, $update) {

	// check if the form was submitted corrected
	if  ( (! isset($_POST["linklist-meta-box-nonce"])   || (! wp_verify_nonce($_POST["linklist-meta-box-nonce"], basename(__FILE__))))
	     and (! isset($_POST["linklist-quick-edit-nonce"]) || (! wp_verify_nonce($_POST["linklist-quick-edit-nonce"], basename(__FILE__))))
		)
			return $post_id;

	if( ! current_user_can('edit_post', $post_id))
		return $post_id;

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return $post_id;

	// save quick edit data
	if (isset($_POST["linklist-quick-edit-nonce"])) // save quick edit
	{
		update_post_meta($post_id, 'linklist-display', 'yes' == $_POST['linklist-selectbox'] ? 1:0);
	}

	else // save edit post page
		update_post_meta($post_id, 'linklist-display', isset($_POST['linklist-display']) ? 1 : 0);
}
/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_add_posts_column( $columns, $post_type ) {
	$types = array('post', 'page');
	if (in_array( $post_type, $types) )
		$columns[ 'linklist' ] = 'Linklist';
	return $columns;
}/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_populate_columns( $column_name, $post_id) {
	if ($column_name == 'linklist') {
		$id      = sprintf('id="linklist-%s"', $post_id);
		$image   = sprintf('<img src="%s" %s height="24" width="24">', plugins_url( 'check.png', __FILE__ ), $id);
		$display = get_post_meta($post_id, 'linklist-display', true) == ('0'|'') ? '': $image;

		printf('<div id="linklist-%s">%s</div>', $post_id, $display);
	} //if
} //link_list_populate_columns
/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_add_to_quick_edit_custom_box($column_name, $post_type) {
global $post_id;

	wp_nonce_field(basename(__FILE__), "linklist-quick-edit-nonce");

	$types = array('post', 'page');
	if (in_array( $post_type, $types) ) {
		?><fieldset class="inline-edit-col-right">
			<div class="inline-edit-group">
				<label>
					<span class="title">Linklist</span>

					<select name="linklist-selectbox" id="linklist-selectbox">
					<option value="yes"> <?php _e('Display'); ?></option>
					<option value="no"> <?php _e('Hide'); ?></option>
					</select>


				</label>
			</div>
		</fieldset><?php
	}
}
/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_add_to_bulk_edit_custom_box($column_name, $post_type) {
	global $post_id;

	wp_nonce_field(basename(__FILE__), "linklist-quick-edit-nonce");

	$types = array('post', 'page');
	if (in_array( $post_type, $types) ) {
		?><fieldset class="inline-edit-col-right">
		<div class="inline-edit-group">
			<label>
				<span class="title">Linklist</span>

				<select name="linklist-selectbox" id="linklist-selectbox">
					<option value="nochange" selected="selected">&mdash; No Change &mdash;</option>
					<option value="yes"> <?php _e('Display'); ?></option>
					<option value="no"> <?php _e('Hide'); ?></option>
				</select>


			</label>
		</div>
		</fieldset><?php
	}
}
/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_enqueue_edit_scripts() {
	wp_enqueue_script( 'linklist-admin-edit', plugins_url( 'linklist.js', __FILE__ ), array( 'jquery', 'inline-edit-post' ), '', true );
}
/* ------------------------------------------------------------------------------------------------------------------ */
function linklist_save_bulk_edit() {
	$post_ids = ( isset( $_POST[ 'post_ids' ] ) && !empty( $_POST[ 'post_ids' ] ) ) ? $_POST[ 'post_ids' ] : array();
	$linklist_state = ( isset( $_POST[ 'linklist_state' ] ) && !empty( $_POST[ 'linklist_state' ] ) ) ? $_POST[ 'linklist_state' ] : NULL;

	if (empty ($post_ids))
		return;
	if ($linklist_state == 'nochange')
		return;

	foreach ($post_ids as $post_id)
		update_post_meta($post_id, 'linklist-display', 'yes' == $linklist_state? 1 : 0);

}
/* ------------------------------------------------------------------------------------------------------------------ */

if (is_admin()) {
    require_once('linklist-options.php');
	register_activation_hook( __FILE__, 'llactivate' );

	// add per post display support
	add_action( 'add_meta_boxes', 'linklist_AddMetaBox');
	add_action( "save_post", "save_linklist_meta_box", 10, 3);
	add_filter( 'manage_posts_columns', 'linklist_add_posts_column', 10, 2 );
	add_action( 'manage_posts_custom_column', 'linklist_populate_columns', 10, 2 );
	add_action( 'bulk_edit_custom_box', 'linklist_add_to_bulk_quick_edit_custom_box', 10, 2 );
	add_action( 'quick_edit_custom_box', 'linklist_add_to_quick_edit_custom_box', 10, 2 );
	add_action( 'bulk_edit_custom_box', 'linklist_add_to_bulk_edit_custom_box', 10, 2 );
	add_action( 'admin_print_scripts-edit.php', 'linklist_enqueue_edit_scripts' );
	add_action( 'wp_ajax_linklist_save_bulk_edit', 'linklist_save_bulk_edit');
}

$priority = get_option('linklist_priority');
if (! $priority)
    $priority = 10;

add_filter('the_content', 'create_linklist', $priority);
