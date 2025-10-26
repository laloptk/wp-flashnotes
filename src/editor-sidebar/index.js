import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { Annotation } from '@wpfn/components';
import { __ } from '@wordpress/i18n';
import { dispatch, useSelect } from '@wordpress/data';
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useRelatedPost } from '@wpfn/hooks';
import apiFetch from '@wordpress/api-fetch';

function FlashNotesSidebar() {
	const [ hasRelationship, setHasRelationship ] = useState(false);
	const [ syncBtnText, setSyncBtnText ] = useState(
		__( 'Sync Study Set', 'wp-flashnotes' )
	);
	const [ isSyncing, setIsSyncing ] = useState( false );

	const { postId, postType, isSaving, isDirty } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
			isSaving: editor.isSavingPost(),
			isDirty: editor.isEditedPostDirty(),
		};
	}, [] );

	const { records, relationship, loading } = useRelatedPost( { postType, postId } );

	const disabledByEditor = isSaving || isDirty;

	const handleSync = useCallback( async () => {
		if ( disabledByEditor ) return;

		setIsSyncing( true );
		setSyncBtnText( __( 'Syncing…', 'wp-flashnotes' ) );

		try {
			const originPostId =
				relationship?.item?.post_id ??
				( postType !== 'studyset' ? postId : null );

			if ( ! originPostId ) throw new Error( 'Missing origin post ID' );

			await apiFetch( {
				path: '/wpfn/v1/studyset/sync',
				method: 'POST',
				data: { origin_post_id: originPostId },
			} );

			setSyncBtnText( __( 'Synced!', 'wp-flashnotes' ) );
			dispatch( 'core/notices' ).createNotice(
				'success',
				__( 'Study set synced!', 'wp-flashnotes' ),
				{ type: 'snackbar' }
			);
			setHasRelationship(true);
		} catch ( e ) {
			console.error( 'Sync failed:', e );
			setSyncBtnText( __( 'Error syncing', 'wp-flashnotes' ) );
			dispatch( 'core/notices' ).createNotice(
				'error',
				__( 'Error syncing study set', 'wp-flashnotes' ),
				{ type: 'snackbar' }
			);
		} finally {
			setIsSyncing( false );
			setSyncBtnText( __( 'Sync Study Set', 'wp-flashnotes' ) );
		}
	}, [ postId, postType, relationship, disabledByEditor ] );

	useEffect( () => {
		if ( postType === 'post' || postType === 'studyset' ) {
			dispatch( 'core/edit-post' ).openGeneralSidebar( 'wp-flashnotes-sidebar' );
		}
	}, [ postType ] );

	useEffect( () => {
		if( ! loading ) {
			setHasRelationship(Boolean( relationship?.item ) );
		}
	}, [loading, relationship]);

	const studysetId =
		records.studysetRecord?.id ?? relationship?.item?.set_post_id ?? null;
	const originPostId =
		records.originPostRecord?.id ?? relationship?.item?.post_id ?? null;
	const sameIds = studysetId && originPostId && studysetId === originPostId;

	const renderDisabledNotice = disabledByEditor && (
		<Notice status="info" isDismissible={ false }>
			{ __( 'You need to save the post before syncing or attaching a Study Set.', 'wp-flashnotes' ) }
		</Notice>
	);

	return (
		<>
			<PluginSidebarMoreMenuItem target="wp-flashnotes-sidebar">
				{ __( 'WP FlashNotes', 'wp-flashnotes' ) }
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name="wp-flashnotes-sidebar"
				title={ __( 'FlashNotes', 'wp-flashnotes' ) }
				icon="edit"
			>
				<PanelBody>
					{ loading && (
						<div style={ { textAlign: 'center', margin: '1em 0' } }>
							<Spinner />
						</div>
					) }

					{ renderDisabledNotice }

					{ postType !== 'studyset' && ! loading && (
						<>
							{ ! hasRelationship ? (
								<div>
									<Annotation
										prefix={ __( 'Study set not attached:', 'wp-flashnotes' ) }
									>
										<p>
											{ __( 'This post does not have a study set linked to it.', 'wp-flashnotes' ) }{' '}
											{ __( 'To generate one, click the button below.', 'wp-flashnotes' ) }
										</p>
									</Annotation>

									<Button
										variant="primary"
										onClick={ handleSync }
										disabled={ isSyncing || disabledByEditor }
									>
										{ isSyncing ? <Spinner /> : __( 'Attach Study Set', 'wp-flashnotes' ) }
									</Button>
								</div>
							) : (
								<div>
									<Annotation
										prefix={ __( 'Study set attached:', 'wp-flashnotes' ) }
									>
										<p>
											{ __( 'A study set is related to this post.', 'wp-flashnotes' ) }{' '}
											<a
												href={ records.studysetRecord?.link || '#' }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ records.studysetRecord?.title?.rendered ||
													__( 'View study set', 'wp-flashnotes' ) }
											</a>
										</p>
										<p>
											{ __( 'You can sync the FlashNotes blocks in this post to the study set below.', 'wp-flashnotes' ) }
										</p>
									</Annotation>

									<Button
										variant="secondary"
										onClick={ handleSync }
										disabled={ isSyncing || disabledByEditor }
									>
										{ isSyncing ? <Spinner /> : syncBtnText }
									</Button>
								</div>
							) }
						</>
					) }

					{ postType === 'studyset' && ! loading && (
						<>
							{ ! hasRelationship || sameIds ? (
								<Annotation prefix={ __( 'Direct study set:', 'wp-flashnotes' ) }>
									<p>{ __( 'This study set was created directly and does not have an origin post.', 'wp-flashnotes' ) }</p>
								</Annotation>
							) : (
								<div>
									<Annotation
										prefix={ __( 'Origin post attached:', 'wp-flashnotes' ) }
									>
										<p>
											{ __( 'This study set was created from another post.', 'wp-flashnotes' ) }{' '}
											<a
												href={ records.originPostRecord?.link || '#' }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ records.originPostRecord?.title?.rendered ||
													__( 'View origin post', 'wp-flashnotes' ) }
											</a>
										</p>
										<p>
											{ __( 'You can sync the FlashNotes from the origin post into this study set below.', 'wp-flashnotes' ) }
										</p>
									</Annotation>

									<Button
										variant="secondary"
										onClick={ handleSync }
										disabled={ isSyncing || disabledByEditor }
									>
										{ isSyncing ? <Spinner /> : __( 'Sync from Origin Post', 'wp-flashnotes' ) }
									</Button>
								</div>
							) }
						</>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

registerPlugin( 'wp-flashnotes-editor-sidebar', { render: FlashNotesSidebar } );
