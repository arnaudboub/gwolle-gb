<?php
/*
 * Editor for editing entries and writing admin entries.
 */

// No direct calls to this script
if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) {
	die('No direct calls allowed!');
}


function gwolle_gb_page_editor() {
	global $wpdb; // FIXME

	if ( function_exists('current_user_can') && !current_user_can('moderate_comments') ) {
		die(__('Cheatin&#8217; uh?'));
	}

	if (!get_option('gwolle_gb_version')) {
		// FIXME: do this on activation
		gwolle_gb_installSplash();
	} else {

		$gwolle_gb_errors = '';
		$gwolle_gb_messages = '';

		$sectionHeading = __('Edit guestbook entry', GWOLLE_GB_TEXTDOMAIN);

		// Always fetch the requested entry, so we can compare the $entry and the $_POST.
		$entry = new gwolle_gb_entry();

		if ( isset($_POST['entry_id']) ) { // _POST has preference over _GET
			$entry_id = intval($_POST['entry_id']);
		} else if ( isset($_GET['entry_id']) ) {
			$entry_id = intval($_GET['entry_id']);
		}
		if ( isset($entry_id) && $entry_id > 0 ) {
			$result = $entry->load( $entry_id );
			if ( !$result ) {
				$gwolle_gb_messages .= '<p class="error">' . __('Entry could not be found.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
				$gwolle_gb_errors = 'error';
				$sectionHeading = __('Guestbook entry (error)', GWOLLE_GB_TEXTDOMAIN);
			}
		} else {
			$sectionHeading = __('New guestbook entry', GWOLLE_GB_TEXTDOMAIN);
		}


		/*
		 * Handle the $_POST
		 */
		if ( isset($_POST['gwolle_gb_page']) && $_POST['gwolle_gb_page'] == 'editor' && $gwolle_gb_errors == '' ) {

			if ( !isset($_POST['entry_id']) || $_POST['entry_id'] != $entry->get_id() ) {
				$gwolle_gb_messages .= '<p class="error">' . __('Something strange happened.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
				$gwolle_gb_errors = 'error';
			} else if ( $_POST['entry_id'] > 0 && $entry->get_id() > 0 ) {

				/* Check for changes, and update accordingly. This is on an Existing Entry */
				$changed = false;

				/* Set as checked or unchecked, and by whom */
				if ( isset($_POST['ischecked']) && $_POST['ischecked'] == 'on' ) {
					if ( $_POST['ischecked'] == 'on' && $entry->get_ischecked() == 0 ) {
						$entry->set_ischecked( true );
						$user_id = get_current_user_id(); // returns 0 if no current user
						$entry->set_checkedby( $user_id );
						gwolle_gb_add_log_entry( $entry->get_id(), 'entry-checked' );
						$changed = true;
					}
				} else if ( $entry->get_ischecked() == 1 ) {
					$entry->set_ischecked( false );
					gwolle_gb_add_log_entry( $entry->get_id(), 'entry-unchecked' );
					$changed = true;
				}

				/* Set as spam or not, and submit as ham or spam to Akismet service */
				if ( isset($_POST['isspam']) && $_POST['isspam'] == 'on' ) {
					if ( $_POST['isspam'] == 'on' && $entry->get_isspam() == 0 ) {
						$entry->set_isspam( true );
						$result = gwolle_gb_akismet( $entry, 'submit-spam' );
						if ( $result ) {
							$gwolle_gb_messages .= '<p>' . __('Submitted as Spam to the Akismet service.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
						}
						gwolle_gb_add_log_entry( $entry->get_id(), 'marked-as-spam' );
						$changed = true;
					}
				} else if ( $entry->get_isspam() == 1 ) {
					$entry->set_isspam( false );
					$result = gwolle_gb_akismet( $entry, 'submit-ham' );
					if ( $result ) {
						$gwolle_gb_messages .= '<p>' . __('Submitted as Ham to the Akismet service.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
					}
					gwolle_gb_add_log_entry( $entry->get_id(), 'marked-as-not-spam' );
					$changed = true;
				}

				/* Set as trash or not */
				if ( isset($_POST['isdeleted']) && $_POST['isdeleted'] == 'on' ) {
					if ( $_POST['isdeleted'] == 'on' && $entry->get_isdeleted() == 0 ) {
						$entry->set_isdeleted( true );
						gwolle_gb_add_log_entry( $entry->get_id(), 'entry-trashed' );
						$changed = true;
					}
				} else if ( $entry->get_isdeleted() == 1 ) {
					$entry->set_isdeleted( false );
					gwolle_gb_add_log_entry( $entry->get_id(), 'entry-untrashed' );
					$changed = true;
				}

				/* Check if the content changed, and update accordingly */
				if ( isset($_POST['gwolle_gb_content']) && $_POST['gwolle_gb_content'] != '' ) {
					if ( $_POST['gwolle_gb_content'] != $entry->get_content() ) {
						$entry->set_content( $_POST['gwolle_gb_content'] );
						gwolle_gb_add_log_entry( $entry->get_id(), 'entry-edited' );
						$changed = true;
					}
				}

				/* Check if the website changed, and update accordingly */
				if ( isset($_POST['gwolle_gb_author_website']) ) {
					if ( $_POST['gwolle_gb_author_website'] != $entry->get_author_website() ) {
						$entry->set_author_website( $_POST['gwolle_gb_author_website'] );
						gwolle_gb_add_log_entry( $entry->get_id(), 'entry-edited' );
						$changed = true;
					}
				}

				/* Check if the author_origin changed, and update accordingly */
				if ( isset($_POST['gwolle_gb_author_origin']) ) {
					if ( $_POST['gwolle_gb_author_origin'] != $entry->get_author_origin() ) {
						$entry->set_author_origin( $_POST['gwolle_gb_author_origin'] );
						gwolle_gb_add_log_entry( $entry->get_id(), 'entry-edited' );
						$changed = true;
					}
				}

				if ( $changed ) {
					$result = $entry->save();
					if ($result ) {
						$gwolle_gb_messages .= '<p>' . __('Changes saved.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
					} else {
						$gwolle_gb_messages .= '<p>' . __('Error happened during saving.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
						$gwolle_gb_errors = 'error';
					}
				} else {
					$gwolle_gb_messages .= '<p>' . __('Entry was not changed.', GWOLLE_GB_TEXTDOMAIN) . '</p>';

				}
			} else if ( $_POST['entry_id'] == 0 && $entry->get_id() == 0 ) {

				/* Check for input, and save accordingly. This is on a New Entry, so no logging */
				$saved = false;
				$data = Array();

				/* Set as checked anyway, new entry is always by an admin */
				$data['ischecked'] = true;
				$user_id = get_current_user_id(); // returns 0 if no current user
				$data['checkedby'] = $user_id;
				$data['authoradminid'] = $user_id;

				/* Set metadata of the admin */
				$userdata = get_userdata( $user_id );

				if (is_object($userdata)) {
					if ( isset( $userdata->display_name ) ) {
						$author_name = $userdata->display_name;
					} else {
						$author_name = $userdata->user_login;
					}
					$author_email = $userdata->user_email;
				}
				$data['author_name'] = $author_name;
				$data['author_email'] = $author_email;

				/* Set as Not Spam */
				$data['isspam'] = false;

				/* Do not set as trash */
				$data['isdeleted'] = false;

				/* Check if the content is filled in, and update accordingly */
				if ( isset($_POST['gwolle_gb_content']) && $_POST['gwolle_gb_content'] != '' ) {
					$data['content'] = $_POST['gwolle_gb_content'];
					$saved = true;
				} else {
					$gwolle_gb_messages .= '<p>' . __('Entry has no content, even though that is mandatory.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
					$gwolle_gb_errors = 'error';
				}

				/* Check if the website changed, and update accordingly */
				if ( isset($_POST['gwolle_gb_author_website']) ) {
					if ( $_POST['gwolle_gb_author_website'] != '' ) {
						$data['author_website'] = $_POST['gwolle_gb_author_website'];
					} else {
						$data['author_website'] = home_url();
					}
				}

				/* Check if the author_origin changed, and update accordingly */
				if ( isset($_POST['gwolle_gb_author_origin']) ) {
					if ( $_POST['gwolle_gb_author_origin'] != '' ) {
						$data['author_origin'] = $_POST['gwolle_gb_author_origin'];
					}
				}

				/* Network Information */
				$entry->set_author_ip( $_SERVER['REMOTE_ADDR'] );
				$entry->set_author_host( gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) );

				$result1 = $entry->set_data( $data );
				if ( $saved ) {
					$result2 = $entry->save();
					if ( $result1 && $result2 ) {
						$gwolle_gb_messages .= '<p>' . __('Entry saved.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
					} else {
						$gwolle_gb_messages .= '<p>' . __('Error happened during saving.', GWOLLE_GB_TEXTDOMAIN) . '</p>';
						$gwolle_gb_errors = 'error';
					}
				} else {
					$gwolle_gb_messages .= '<p>' . __('Entry was not saved.', GWOLLE_GB_TEXTDOMAIN) . '</p>';

				}

			}
		}

		// FIXME: reload the entry, just for consistency?

		/*
		 * Build the Page and the Form
		 */
		?>
		<div class="wrap gwolle_gb">
			<div id="icon-gwolle-gb"><br /></div>
			<h2><?php echo $sectionHeading; ?></h2>

			<?php
			if ( $gwolle_gb_messages ) {
				echo '
					<div id="message" class="updated fade ' . $gwolle_gb_errors . ' ">' .
						$gwolle_gb_messages .
					'</div>';
			}
			?>

			<form name="gwolle_gb_editor" id="gwolle_gb_editor" method="POST" action="" accept-charset="UTF-8">
				<input type="hidden" name="gwolle_gb_page" value="editor" />
				<input type="hidden" name="entry_id" value="<?php echo $entry->get_id(); ?>" />

				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<div id="side-info-column" class="inner-sidebar">
						<div id='side-sortables' class='meta-box-sortables'>

							<div id="submitdiv" class="postbox">
								<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
								<h3 class='hndle'><span><?php _e('Options', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
								<div class="inside">
									<div class="submitbox" id="submitpost">
										<div id="minor-publishing">
											<div id="misc-publishing-actions">
												<div class="misc-pub-section misc-pub-section-last">

													<?php
													$class = '';
													// Attach 'spam' to class if the entry is spam
													if ( $entry->get_isspam() === 1 ) {
														$class .= ' spam';
													}

													// Attach 'trash' to class if the entry is in trash
													if ( $entry->get_isdeleted() === 1 ) {
														$class .= ' trash';
													}

													// Attach 'visible/invisible' to class
													if ( $entry->get_id() == 0 ) {
														$class .= ' invisible';
													} else {
														if ( $entry->get_isspam() === 1 || $entry->get_isdeleted() === 1 || $entry->get_ischecked() === 0 ) {
															$class .= ' invisible';
														} else {
															$class .= ' visible';
														}
													}

													// Optional Icon column where CSS is being used to show them or not
													if ( get_option('gwolle_gb-showEntryIcons', 'true') === 'true' ) { ?>
														<span class="entry-icons <?php echo $class; ?>">
															<span class="visible-icon"></span>
															<span class="invisible-icon"></span>
															<span class="spam-icon"></span>
															<span class="trash-icon"></span>
														</span>
														<?php
													}

													if ( $entry->get_id() == 0 ) {
														echo "<h3>" . __('This entry is Not Visible.', GWOLLE_GB_TEXTDOMAIN) . "</h3>";
													} else {
														if ($entry->get_ischecked() == 1 && $entry->get_isspam() == 0 && $entry->get_isdeleted() == 0 ) {
															echo "<h3>" . __('This entry is Visible.', GWOLLE_GB_TEXTDOMAIN) . "</h3>";
														} else {
															echo "<h3>" . __('This entry is Not Visible.', GWOLLE_GB_TEXTDOMAIN) . "</h3>";
														}
													} ?>

													<label for="ischecked" class="selectit">
														<input id="ischecked" name="ischecked" type="checkbox" <?php
															if ($entry->get_ischecked() == '1') {
																echo 'checked="checked"';
															}
															?> />
														<?php
														if ($entry->get_ischecked() == '0') {
															_e('This entry is Not Checked.', GWOLLE_GB_TEXTDOMAIN);
														} else {
															_e('This entry is Checked', GWOLLE_GB_TEXTDOMAIN);
														} ?>
													</label>

													<br />
													<label for="isspam" class="selectit">
														<input id="isspam" name="isspam" type="checkbox" <?php
															if ($entry->get_isspam() == '1') {
																echo 'checked="checked"';
															}
															?> />
														<?php
														if ($entry->get_isspam() == '0') {
															_e('This entry is Not marked as Spam.', GWOLLE_GB_TEXTDOMAIN);
														} else {
															_e('This entry is marked as Spam', GWOLLE_GB_TEXTDOMAIN);
														} ?>
													</label>

													<br />
													<label for="isdeleted" class="selectit">
														<input id="isdeleted" name="isdeleted" type="checkbox" <?php
															if ($entry->get_isdeleted() == '1') {
																echo 'checked="checked"';
															}
															?> />
														<?php
														if ($entry->get_isdeleted() == '0') {
															_e('This entry is Not in Trash.', GWOLLE_GB_TEXTDOMAIN);
														} else {
															_e('This entry is in Trash', GWOLLE_GB_TEXTDOMAIN);
														} ?>
													</label>

												</div>
											</div><!-- 'misc-publishing-actions' -->
											<div class="clear"></div>
										</div> <!-- minor-publishing -->

										<div id="major-publishing-actions">
											<div id="publishing-action">
												<input name="save" type="submit" class="button-primary" id="publish" tabindex="4" accesskey="p" value="<?php _e('Save', GWOLLE_GB_TEXTDOMAIN); ?>" />
											</div> <!-- publishing-action -->
											<div class="clear"></div>
										</div><!-- 'major-publishing-actions' -->
									</div><!-- 'submitbox' -->
								</div><!-- 'inside' -->
							</div><!-- 'submitdiv' -->

							<div id="gwolle_gb-entry-details" class="postbox " >
								<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
								<h3 class='hndle'><span><?php _e('Details', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
								<div class="inside">
									<div class="tagsdiv" id="post_tag">
										<p>
										<?php _e('Author', GWOLLE_GB_TEXTDOMAIN); ?>: <span><?php
											// FIXME: use this formatting on frontend as well
											if ( $entry->get_author_name() ) {
												echo stripslashes(htmlentities( $entry->get_author_name() ));
											} else {
												echo '<i>(' . __('Unknown', GWOLLE_GB_TEXTDOMAIN) . ')</i>';
											} ?>
										</span>
										<br />
										<?php _e('E-Mail', GWOLLE_GB_TEXTDOMAIN); ?>: <span><?php
											if (strlen(str_replace( ' ', '', $entry->get_author_email() )) > 0) {
												echo stripslashes(htmlentities($entry->get_author_email()));
											} else {
												echo '<i>(' . __('Unknown', GWOLLE_GB_TEXTDOMAIN) . ')</i>';
											} ?>
										</span>
										<br />
										<?php _e('Written', GWOLLE_GB_TEXTDOMAIN); ?>: <span><?php
											if ( $entry->get_date() > 0 ) {
												echo date_i18n( get_option('date_format'), $entry->get_date() ) . ', ';
												echo date_i18n( get_option('time_format'), $entry->get_date() );
											} else {
												echo '(' . __('Not yet', GWOLLE_GB_TEXTDOMAIN) . ')';
											} ?>
										</span>
										<br />
										<?php _e("Author's IP-address", GWOLLE_GB_TEXTDOMAIN); ?>: <span><?php
											if (strlen( $entry->get_author_ip() ) > 0) {
												echo '<a href="http://www.db.ripe.net/whois?form_type=simple&searchtext=' . $entry->get_author_ip() . '"
														title="' . __('Whois search for this IP', GWOLLE_GB_TEXTDOMAIN) . '" target="_blank">
															' . $entry->get_author_ip() . '
														</a>';
											} else {
												echo '<i>(' . __('Unknown', GWOLLE_GB_TEXTDOMAIN) . ')</i>';
											} ?>
										</span>
										<br />
										<?php _e('Host', GWOLLE_GB_TEXTDOMAIN); ?>: <span><?php
											if (strlen( $entry->get_author_host() ) > 0) {
												echo $entry->get_author_host();
											} else {
												echo '<i>(' . __('Unknown', GWOLLE_GB_TEXTDOMAIN) . ')</i>';
											} ?>
										</span>
										</p>
									</div> <!-- tagsdiv -->
								</div>
							</div><!-- postbox -->

							<div id="tagsdiv-post_tag" class="postbox">
								<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
								<h3 class='hndle'><span><?php _e('Entry log', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
								<div class="inside">
									<div class="tagsdiv" id="post_tag">
										<div id="categories-pop" class="tabs-panel" style="max-height:400px;overflow:auto;"> <?php /* FIXME: place in CSS file */ ?>
											<ul>
											<?php
											if ($entry->get_date() > 0) {
												echo '<li>';
												echo date_i18n( get_option('date_format'), $entry->get_date() ) . ', ';
												echo date_i18n( get_option('time_format'), $entry->get_date() );
												echo ': ' . __('Written', GWOLLE_GB_TEXTDOMAIN) . '</li>';

												$log_entries = gwolle_gb_get_log_entries( $entry->get_id() );
												if ( is_array($log_entries) && count($log_entries) > 0 ) {
													foreach ($log_entries as $log_entry) {
														echo '<li>' . $log_entry['msg_html'] . '</li>';
													}
												}
											} else {
												echo '<li>(' . __('No log yet.', GWOLLE_GB_TEXTDOMAIN) . ')</li>';
											}
											?>
											</ul>
										</div>
									</div>
								</div>
							</div><!-- postbox -->
						</div><!-- 'side-sortables' -->
					</div><!-- 'side-info-column' -->

					<div id="post-body">
						<div id="post-body-content">
							<?php // FIXME: add labels ?>
							<div id='normal-sortables' class='meta-box-sortables'>
								<div id="authordiv" class="postbox " >
									<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
									<h3 class='hndle'><span><?php _e('Guestbook entry', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
									<div class="inside">
										<textarea rows="10" cols="56" name="gwolle_gb_content" tabindex="1"><?php echo gwolle_gb_output_to_input_field( $entry->get_content() ); ?></textarea>
										<?php
										if (get_option('gwolle_gb-showLineBreaks', 'false') == 'false') {
											echo '<p>' . str_replace('%1', 'admin.php?page=' . GWOLLE_GB_FOLDER . '/settings.php', __('Line breaks will not be visible to the visitors due to your <a href="%1">settings</a>.', GWOLLE_GB_TEXTDOMAIN)) . '</p>';
										} ?>
									</div>
								</div>
								<div id="authordiv" class="postbox " >
									<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
									<h3 class='hndle'><span><?php _e('Homepage', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
									<div class="inside">
										<input type="text" name="gwolle_gb_author_website" size="58" tabindex="2" value="<?php echo gwolle_gb_output_to_input_field( $entry->get_author_website() ); ?>" id="author_website" />
										<p><?php _e("Example: <code>http://www.example.com/</code>", GWOLLE_GB_TEXTDOMAIN); ?></p>
									</div>
								</div>
								<div id="authordiv" class="postbox ">
									<div class="handlediv" title="<?php _e('Click to open or close', GWOLLE_GB_TEXTDOMAIN); ?>"><br /></div>
									<h3 class='hndle'><span><?php _e('Origin', GWOLLE_GB_TEXTDOMAIN); ?></span></h3>
									<div class="inside">
										<input type="text" name="gwolle_gb_author_origin" size="58" tabindex="3" value="<?php echo gwolle_gb_output_to_input_field( $entry->get_author_origin() ); ?>" id="author_origin" />
									</div>
								</div>
							</div><!-- 'normal-sortables' -->
						</div><!-- 'post-body-content' -->
					</div>
					<br class="clear" />
				</div><!-- /poststuff -->
			</form>
		</div>

		<?php
	}
}

