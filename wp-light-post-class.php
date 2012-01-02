<?php

/*
	Support class Light Post
	Copyright (c) 2010, 2011, 2012 by Marcel Bokhorst
*/

// Define constants
define('c_wplp_text_domain', 'wp-light-post');
define('c_wplp_option_version', 'wplp_version');
define('c_wplp_option_redirect', 'wplp_redirect');
define('c_wplp_option_jquery', 'wplp_jquery');
define('c_wplp_option_height', 'wplp_height');
define('c_wplp_option_posts', 'wplp_posts');
define('c_wplp_option_clean', 'wplp_clean');
define('c_wplp_option_donated', 'wplp_donated');

require_once(ABSPATH . '/wp-admin/includes/plugin.php');
require_once(ABSPATH . WPINC . '/pluggable.php');

// Define class
if (!class_exists('WPLightPost')) {
	class WPLightPost {
		// Constructor
		function __construct() {
			// Register (de)activation hook
			$bt = debug_backtrace();
			$file = $bt[0]['file'];
			register_activation_hook($file, array(&$this, 'Activate'));
			register_deactivation_hook($file, array(&$this, 'Deactivate'));

			// Register actions/filters
			add_action('init', array(&$this, 'Init'));
			if (is_admin()) {
				add_action('admin_menu', array(&$this, 'Admin_menu'));
				add_action('post_submitbox_start', array(&$this, 'Post_submitbox_start'));
			}
			add_filter('login_redirect', array(&$this, 'Login_redirect'));
			add_filter('post_row_actions', array(&$this, 'Post_row_actions'), 10, 2);
			add_action('wp_ajax_light', array(&$this, 'Check_ajax'));
		}

		// Handle plugin activation
		function Activate() {
			if (!get_option(c_wplp_option_version)) {
				// Set version
				update_option(c_wplp_option_version, 1);
				update_option(c_wplp_option_redirect, true);
			}
		}

		// Handle plugin deactivation
		function Deactivate() {
			// Cleanup if requested
			if (get_option(c_wplp_option_clean)) {
				// Delete options
				delete_option(c_wplp_option_version);
				delete_option(c_wplp_option_redirect);
				delete_option(c_wplp_option_jquery);
				delete_option(c_wplp_option_height);
				delete_option(c_wplp_option_posts);
				delete_option(c_wplp_option_clean);
				delete_option(c_wplp_option_donated);
			}
		}

		// Handle initialize
		function Init() {
			if (is_admin()) {
				// I18n
				load_plugin_textdomain(c_wplp_text_domain, false, dirname(plugin_basename(__FILE__)));

				// Load style sheet
				$css_name = 'wp-light-post.css';
				$upload_dir = wp_upload_dir();
				if (file_exists($upload_dir['basedir'] . '/' . $css_name))
					$css_url = $upload_dir['baseurl'] . '/' . $css_name;
				else
					$css_url = plugins_url($css_name, __FILE__);
				wp_register_style('light-post-style', $css_url);
				wp_enqueue_style('light-post-style');

				register_setting('wp-light-post', c_wplp_option_redirect);
				register_setting('wp-light-post', c_wplp_option_jquery);
				register_setting('wp-light-post', c_wplp_option_height);
				register_setting('wp-light-post', c_wplp_option_posts);
				register_setting('wp-light-post', c_wplp_option_clean);
				register_setting('wp-light-post', c_wplp_option_donated);
			}
		}

		// Extend post meta box
		function Post_submitbox_start() {
			echo '<div><a href="' . admin_url('admin-ajax.php?action=light&post_ID=') . self::Get_post_id() . '">' . __('Light Post', c_wplp_text_domain) . '</a></div>';
		}

		// Helper: get post id
		function Get_post_id($postid = false) {
			global $post;
			$postid = ($postid ? $postid : $post->ID);
			$revision = wp_is_post_revision($postid);
			return ($revision ? $revision : $postid);
		}

		// Filter: redirect login
		function Login_redirect($redirect_to) {
			global $user;
			if (get_option(c_wplp_option_redirect) &&
				!is_wp_error($user) &&
				$user->has_cap('publish_posts') &&
				$redirect_to == admin_url())
				return admin_url('admin-ajax.php?action=light');
			else
				return $redirect_to;
		}

		// Filter: add row action
		function Post_row_actions($actions, $post = null) {
			if ($post)
				$actions['light-post'] = '<a href="' . admin_url('admin-ajax.php?action=light&post_ID='. $post->ID) . '">' . __('Light Post', c_wplp_text_domain) . '</a>';
			return $actions;
		}

		// Add options page
		function Admin_menu() {
			add_options_page(
				__('Light Post', c_wplp_text_domain),
				__('Light Post', c_wplp_text_domain),
				'manage_options',
				__FILE__,
				array(&$this, 'Options_page'));
		}

		// Render options page
		function Options_page() {
			if (current_user_can('manage_options')) {
				echo '<div class="wrap">';
				$this->Render_info_panel();
?>
				<div id="light-post-options">
				<h2><?php _e('Light Post', c_wplp_text_domain); ?></h2>

				<form method="post" action="options.php">
				<?php wp_nonce_field('update-options'); ?>
				<?php settings_fields('wp-light-post'); ?>

				<table class="form-table">

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_redirect"><?php _e('Redirect login:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_redirect" name="<?php echo c_wplp_option_redirect; ?>" type="checkbox"<?php if (get_option(c_wplp_option_redirect)) echo ' checked="checked"'; ?> />
				</td></tr>

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_jquery"><?php _e('Always dynamic content:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_jquery" name="<?php echo c_wplp_option_jquery; ?>" type="checkbox"<?php if (get_option(c_wplp_option_jquery)) echo ' checked="checked"'; ?> />
				</td></tr>

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_height"><?php _e('Post box height:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_height" name="<?php echo c_wplp_option_height; ?>" type="text" value="<?php echo get_option(c_wplp_option_height); ?>" />
					<span>px</span>
				</td></tr>

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_posts"><?php _e('Number of posts to display:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_posts" name="<?php echo c_wplp_option_posts; ?>" type="text" value="<?php echo get_option(c_wplp_option_posts); ?>" />
				</td></tr>

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_clean"><?php _e('Clean on deactivate:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_clean" name="<?php echo c_wplp_option_clean; ?>" type="checkbox"<?php if (get_option(c_wplp_option_clean)) echo ' checked="checked"'; ?> />
				</td></tr>

				<tr valign="top"><th scope="row">
					<label for="wplp_opt_donated"><?php _e('I have donated to this plugin:', c_wplp_text_domain); ?></label>
				</th><td>
					<input id="wplp_opt_donated" name="<?php echo c_wplp_option_donated; ?>" type="checkbox"<?php if (get_option(c_wplp_option_donated)) echo ' checked="checked"'; ?> />
				</td></tr>

				</table>
<?php
				$options[] = c_wplp_option_redirect;
				$options[] = c_wplp_option_jquery;
				$options[] = c_wplp_option_height;
				$options[] = c_wplp_option_posts;
				$options[] = c_wplp_option_clean;
				$options[] = c_wplp_option_donated;
?>
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="page_options" value="<?php echo implode(',', $options); ?>" />

				<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes', c_wplp_text_domain) ?>" />
				</p>
				</form>
				</div>
				</div>
<?php
			}
			else
				die('Unauthorized');
		}

		function Render_info_panel() {
?>
			<div id="light-post-resources">
			<h3><?php _e('Resources', c_wplp_text_domain); ?></h3>
			<ul>
			<li><a href="http://wordpress.org/extend/plugins/light-post/faq/" target="_blank"><?php _e('Frequently asked questions', c_wplp_text_domain); ?></a></li>
			<li><a href="http://blog.bokhorst.biz/4146/computers-en-internet/wordpress-plugin-light-post/" target="_blank"><?php _e('Support page', c_wplp_text_domain); ?></a></li>
			<li><a href="http://blog.bokhorst.biz/about/" target="_blank"><?php _e('About the author', c_wplp_text_domain); ?></a></li>
			</ul>
<?php		if (!get_option(c_wplp_option_donated)) { ?>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----">
			<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			</form>
<?php		} ?>
			</div>
<?php
		}

		function Check_ajax() {
			self::Handle_request();
			exit();
		}

		// Handle direct request
		function Handle_request() {
			load_plugin_textdomain(c_wplp_text_domain, false, dirname(plugin_basename(__FILE__)));

			header('Content-Type: text/html; charset=' . get_option('blog_charset'));
			if ($_FILES)
				if (current_user_can('upload_files'))
					self::Handle_upload();
				else
					die('Unauthorized');
			else
				if (current_user_can('publish_posts'))
					self::Handle_form();
				else
					die('Unauthorized');
		}

		// Handle image upload
		function Handle_upload() {
			// Security check
			if (!wp_verify_nonce($_REQUEST['nonce'], 'light-post-upload'))
					die('Unauthorized');

			// Perform upload
			$file = self::Upload_image($_FILES['post_upload']);
			if ($file) {
				$title = preg_replace('/\.[^.]+$/', '', basename($file['file']));
				$image_src = self::Resize_image($file, $_REQUEST['size']);
				$attach_id = self::Attach_image($file, $_REQUEST['post_ID'], $title);
				if ($attach_id) {
					echo '<a href="' . $file['url'] . '">';
					echo '<img src="' . $image_src . '" alt="" title="' . $title;
					echo '" class="alignnone size-' . $_REQUEST['size'] . ' wp-image-' . $attach_id . '" />';
					echo '</a>';
				}
			}

			if (!$file || !$attach_id)
				die(__('Upload image failed', c_wplp_text_domain));
		}

		function Upload_image($upload)
		{
			if (file_is_displayable_image($upload['tmp_name'])) {
				$overrides = array('test_form' => false);
				return wp_handle_upload($upload, $overrides);
			}
			return null;
		}

		function Resize_image($file, $size) {
			$resized = image_make_intermediate_size(
				$file['file'],
				get_option($size . '_size_w'),
				get_option($size . '_size_h'),
				get_option($size . '_crop'));
			if ($resized) {
				$upload_dir = wp_upload_dir();
				return $upload_dir['url'] . '/' . $resized['file'];
			}
			return $file['url'];
		}

		function Attach_image($file, $post, $title) {
			$attachment = array
			(
				'post_mime_type' => $file['type'],
				'guid' => $file['url'],
				'post_parent' => $post,
				'post_title' => $title,
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attach_id = wp_insert_attachment($attachment, $file['file'], $post);
			if (is_wp_error($attach_id))
				$attach_id = null;
			else
				wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file['file']));
			return $attach_id;
		}

		// Render post management form
		function Handle_form() {
			global $wpdb;

			// Get/update post
			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'update') {
				// Security check
				check_admin_referer('light-post-form');

				// Get post data
				$the_post = array();
				$the_post['ID'] = $_POST['post_ID'];
				$the_post['post_title'] = $_POST['post_title'];
				$the_post['post_content'] = $_POST['post_content'];

				if (isset($_POST['save']))
					$the_post['post_status'] = 'draft';
				if (isset($_POST['publish']))
					$the_post['post_status'] = 'publish';

				$categories = array();
				$cat = get_category($_POST['post_category']);
				$categories[] = $cat->cat_ID;
				//if ($cat->category_parent)
				//	$categories[] = $cat->category_parent;
				$the_post['post_category'] = $categories;

				$the_post['tags_input'] = $_POST['post_tags'];

				// Update post
				wp_update_post($the_post);
				$post = get_post($the_post['ID']);
			}
			else if (isset($_REQUEST['post_ID']))
				$post = get_post($_REQUEST['post_ID']);
			else {
				$post_id = null;
				if (isset($_REQUEST['action']) && $_REQUEST['action'] != 'new') {
					$query = "SELECT ID FROM $wpdb->posts";
					$query .= " WHERE post_type = 'post'";
					$query .= " AND (post_status = 'draft' OR post_status = 'publish')";
					$query .= " AND post_author = " . $GLOBALS['current_user']->ID;
					$query .= " ORDER BY post_modified DESC";
					$query .= " LIMIT 0,1";
					$post_id = $wpdb->get_var($query);
				}
				if ($post_id)
					$post = get_post($post_id);
				else
					$post = get_default_post_to_edit('post', true);
			}

			$use_jquery = get_option(c_wplp_option_jquery) || (isset($_REQUEST['jquery']) && $_REQUEST['jquery'] == 'true');

?>
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" >
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=<?php echo get_option('blog_charset'); ?>" />
			<title><?php _e('Light Post', c_wplp_text_domain); ?> - <?php echo esc_html($post->post_title); ?></title>
<?php
			// Output scripts
			if ($use_jquery) {
				wp_enqueue_script('jquery');
				wp_enqueue_script('ajaxupload', plugins_url('/js/ajaxupload.js', __FILE__));
				wp_enqueue_script('a-tools', plugins_url('/js/jquery.a-tools.js', __FILE__));
				wp_print_scripts();
			}

			// Locate style sheet
			$css_name = 'wp-light-post.css';
			$upload_dir = wp_upload_dir();
			if (file_exists($upload_dir['basedir'] . '/' . $css_name))
				$css_url = $upload_dir['baseurl'] . '/' . $css_name;
			else
				$css_url = plugins_url($css_name, __FILE__);
?>
			<link rel="stylesheet" id="light-post-css" href="<?php echo $css_url; ?>" type="text/css" media="all" />

<?php		if ($use_jquery) { ?>
				<script type="text/javascript">
				/* <![CDATA[ */
				jQuery(document).ready(function($) {
					/* Instantiate ajax upload */
					var uploader = new AjaxUpload('post_upload', {
						action: '<?php echo admin_url('admin-ajax.php'); ?>',
						name: 'post_upload',
						autoSubmit: true,
						responseType: false,
						onChange: function(file, extension) {},
						onSubmit: function(file, extension) {
							uploader.setData({
								'action': 'light',
								'nonce': '<?php echo wp_create_nonce('light-post-upload'); ?>',
								'size' : $('[name=post_image_size]').val(),
								'post_ID' : '<?php echo $post->ID; ?>'
							});
							this.disable();
						},
						onComplete: function(file, response) {
							this.enable();
							$('[name=post_content]').insertAtCaretPos(response);
						}
					});

					/* Bold */
					$('[name=post_bold_button]').click(function() {
						$('[name=post_content]').replaceSelection(
							'<strong>' + $('[name=post_content]').getSelection().text + '</strong>'
						);
						return false;
					});

					/* Italic */
					$('[name=post_italic_button]').click(function() {
						$('[name=post_content]').replaceSelection(
							'<em>' + $('[name=post_content]').getSelection().text + '</em>'
						);
						return false;
					});

					/* Insert link */
					$('[name=post_link_button]').click(function() {
						var url = prompt('<?php _e('Url', c_wplp_text_domain); ?>', 'http://');
						if (url != null && url != '')
							$('[name=post_content]').replaceSelection(
								'<a href="' + url + '"' +
								($('[name=post_link_check]').is(':checked') ? ' target="_blank">' : '>') +
								$('[name=post_content]').getSelection().text + '</a>'
							);
						return false;
					});

					/* Auto focus */
					if ($('[name=post_content]').val() == '')
						$('[name=post_title]').focus();
					else
						$('[name=post_content]').focus();

					/* Javascript enabled */
					$('#post_javascript').show();
				});
				/* ]]> */
				</script>
<?php		} ?>
			</head>

			<body id="light-post-form">
			<p>
				<a href="<?php echo get_bloginfo('url'); ?>"><?php echo get_bloginfo('title'); ?></a>
				<a href="<?php echo admin_url('index.php'); ?>"><?php _e('Admin', c_wplp_text_domain); ?></a>
				<a href="<?php echo wp_logout_url(); ?>" title="Logout"><?php _e('Logout', c_wplp_text_domain); ?></a>
			</p>

			<form name="post" action="<?php echo admin_url('admin-ajax.php?action=light'); ?>" method="post">
				<?php wp_nonce_field('light-post-form'); ?>
				<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="jquery" value="<?php echo $use_jquery ? 'true' : 'false'; ?>" />
				<div class="light-post-box"><table>
					<tr><td><span class="light-post-title"><?php _e('Title:', c_wplp_text_domain); ?></span></td>
					<td><input type="text" name="post_title" value="<?php echo esc_html($post->post_title); ?>" />
<?php
					// Post status
					if ($post->post_status == 'draft')
						echo '<span class="light-post-draft">' . __('Draft', c_wplp_text_domain) . '</span>';
?>
					</td></tr>

					<tr><td><span class="light-post-title"><?php _e('Tags:', c_wplp_text_domain); ?></span></td>
<?php
					// Tags
					$tags = '';
					$tag_list = get_the_tags($post->ID);
					if ($tag_list)
						foreach ($tag_list as $tag) {
							if ($tags)
								$tags .= ', ';
							$tags .= $tag->name;
						}
?>
					<td><input type="text" name="post_tags" value="<?php echo $tags; ?>" /></td></tr>

					<tr><td><span class="light-post-title"><?php _e('Category:', c_wplp_text_domain); ?></span></td>
					<td><select name="post_category">
<?php
					// Categories
					$post_cats = wp_get_post_categories($post->ID);
					if (!$post_cats)
						$post_cats = array(get_option('default_category'));
					$cargs = array('hide_empty' => 0);
					$categories = get_categories($cargs);
					foreach ($categories as $cat) {
						echo '<option ';
						if ($cat->cat_ID == $post_cats[0])
							echo 'selected="selected" ' ;
						echo 'value="' . $cat->cat_ID . '">';
						$c = $cat;
						while ($c->category_parent) {
							echo '&nbsp;&nbsp;';
							$c = get_category($c->category_parent);
						}
						echo esc_html($cat->cat_name) . '</option>';
					}
?>
					</select></td></tr>
				</table></div>
<?php			if ($use_jquery) { ?>
					<div class="light-post-box" style="display:none;" id="post_javascript">
					<p>
						<input type="button" name="post_bold_button" class="button" value="<?php _e('Bold', c_wplp_text_domain); ?>" />
						<input type="button" name="post_italic_button" class="button" value="<?php _e('Italic', c_wplp_text_domain); ?>" />
						<input type="button" name="post_link_button" class="button" value="<?php _e('Link', c_wplp_text_domain); ?>" />
						<input type="checkbox" name="post_link_check" value="blank" /><?php _e('Blank page', c_wplp_text_domain); ?>
					</p>
					<p>
<?php				if (current_user_can('upload_files')) { ?>
						<select name="post_image_size">
							<option value="thumbnail"><?php _e('Small', c_wplp_text_domain); ?></option>
							<option value="medium" selected="selected"><?php _e('Medium', c_wplp_text_domain); ?></option>
							<option value="large"><?php _e('Large', c_wplp_text_domain); ?></option>
						</select>
						<a id="post_upload" href="#"><?php _e('Image', c_wplp_text_domain); ?></a>
						<span>(&lt;<?php echo ini_get('upload_max_filesize'); ?>)</span>
<?php				} ?>
					</p>
					</div>
<?php			} else { ?>
					<div class="light-post-box" id="post_javascript">
					<p>
						<a href="<?php echo $_SERVER['REQUEST_URI'] . '&jquery=true'; ?>"><?php _e('Dynamic content', c_wplp_text_domain); ?></a>
					</p>
					</div>
<?php			} ?>
<?php
				// Text box
				$height = intval(get_option(c_wplp_option_height));
				if ($height > 0)
					$height = ' style="height: ' . $height . 'px;"';
?>
				<textarea name="post_content" rows="20" cols="80" class="light-post-box"<?php echo $height; ?>><?php echo $post->post_content; ?></textarea>
				<div class="light-post-box">
				<p>
					<input type="submit" name="save" class="button" value="<?php _e($post->post_status == 'draft' ? 'Save Draft' : 'Revert to Draft', c_wplp_text_domain); ?>" />
					<input type="submit" name="publish" class="button-primary" value="<?php _e($post->post_status == 'draft' ? 'Publish' : 'Update', c_wplp_text_domain); ?>" />
				</p>
				<p>
					<a href="<?php echo self::Get_preview_link($post); ?>" target="_blank"><?php _e('Preview', c_wplp_text_domain); ?></a>
					<a href="<?php echo admin_url('post.php?action=edit&post=' . $post->ID); ?>"><?php _e('Rich Edit', c_wplp_text_domain); ?></a>
					<a href="<?php echo admin_url('admin-ajax.php?action=light&action=new', __FILE__); ?>"><?php _e('New post', c_wplp_text_domain); ?></a>
<?php				if (!get_option(c_wplp_option_donated)) { ?>
						<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=AJSBB7DGNA3MJ&lc=US&item_name=Light%20Post%20WordPress%20Plugin&item_number=Marcel%20Bokhorst&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_LG%2egif%3aNonHosted" target="_blank"><?php _e('Donate', c_wplp_text_domain); ?></a>
<?php				} ?>
				</p>
				</div>
				<div class="light-post-box">
				<table id="light-post-list">
<?php
					$posts = intval(get_option(c_wplp_option_posts));
					if ($posts <= 0)
						$posts = 10;
					$query = "SELECT ID, post_title, post_status, post_modified FROM $wpdb->posts";
					$query .= " WHERE post_type = 'post'";
					$query .= " AND (post_status = 'draft' OR post_status = 'publish')";
					$query .= " AND post_author = " . $GLOBALS['current_user']->ID;
					$query .= " ORDER BY post_modified DESC";
					$query .= " LIMIT 0," . $posts;
					$posts = $wpdb->get_results($query);
					foreach ($posts as $post) {
						$url = admin_url('admin-ajax.php?action=light&post_ID='. $post->ID, __FILE__);
						if ($use_jquery)
							$url .= '&jquery=true';
						$title = $post->post_title;
						if (!$title)
							$title = '-';
						echo '<tr><td><a href="' . $url . '">'. esc_html($title) . '</a></td>';
						echo '<td><span class="light-post-draft">' . ($post->post_status == 'draft' ? __('Draft', c_wplp_text_domain) : '') . '</span></td>';
						echo '<td>' . date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_modified)) . '</td>';
						echo '<td><a href="' . admin_url('post.php?action=edit&post=' . $post->ID) . '">' . __('Rich Edit', c_wplp_text_domain) . '</a></td></tr>';
					}
?>
				</table>
				</div>
			</form>
			</body>
			</html>
<?php
		}

		// Helper: get post preview link
		function Get_preview_link($post) {
			if ($post->post_status == 'publish')
				return esc_url(get_permalink($post->ID));
			else
				return esc_url(apply_filters('preview_post_link', add_query_arg('preview', 'true', get_permalink($post->ID))));
		}

		// Helper check environment
		function Check_prerequisites() {
			// Check WordPress version
			global $wp_version;
			if (version_compare($wp_version, '2.9') < 0)
				die('Light Post requires at least WordPress 2.9, installed version is ' . $wp_version);

			// Check basic prerequisities
			WPLightPost::Check_function('register_activation_hook');
			WPLightPost::Check_function('register_deactivation_hook');
			WPLightPost::Check_function('add_action');
			WPLightPost::Check_function('add_filter');
			WPLightPost::Check_function('wp_register_script');
			WPLightPost::Check_function('wp_enqueue_script');
		}

		function Check_function($name) {
			if (!function_exists($name))
				die('Required function "' . $name . '" does not exist');
		}

		// Helper change file name extension
		function Change_extension($filename, $new_extension) {
			return preg_replace('/\..+$/', $new_extension, $filename);
		}
	}
}

?>
