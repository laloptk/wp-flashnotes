import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, Spinner } from '@wordpress/components';
import { Annotation } from '@wpfn/components';
import { __ } from '@wordpress/i18n';
import { dispatch, useSelect, useRef } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { useEffect, useState } from '@wordpress/element';
import { useFetch, useRelatedPost } from '@wpfn/hooks';

function FlashNotesSidebar() {
    const [syncBtnText, setSyncBtnText] = useState('Sync study set');
    
    const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );
    
    useEffect(() => {
        if(postType === 'studyset') {
            setSyncBtnText('Sync study set from post');
        } 
    }, []);
    // Fetch for relationship between post and studyset (sets table endpoint)
    const { record, loading, error } = useRelatedPost( { postType, postId } );
    
    // If relationship exist
        // Show the sync button (sync from origin post if studyset) and a message with a link to the studyset/post
    // If not
        // Show the Create Studyset button

    // Write a function handleOnClick
        // Upsert a studyset via REST API
        // Propagate relationships in DB via REST API
            // Update sets table (Relationship between post and studyset)
            // Update usage table (Relationships between blocks and posts)
            // Update set-card set-note tables
    
    useEffect( () => {
        if ( postType === 'post' || postType === 'studyset' ) {
            dispatch( 'core/edit-post' ).openGeneralSidebar( 'wp-flashnotes-sidebar' );
        }
    }, [ postType ] );
    
    return (
        <>
            <PluginSidebarMoreMenuItem target="wp-flashnotes-sidebar">
                { __( 'FlashNotes', 'wp-flashnotes' ) }
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="wp-flashnotes-sidebar"
                title={ __( 'FlashNotes', 'wp-flashnotes' ) }
                icon="edit"  /* optional icon slug */
            >
                <PanelBody>
                    {(! record )
                        ?
                        <div>
                            <Annotation prefix="Study set not attached: ">
                                <p>
                                    {__('This post does not have a study set linked to it.', 'wp-flashnotes' )} 
                                    {__('To generate one, click on the button below.', 'wp-flashnotes')}
                                </p>
                            </Annotation>
                            <Button variant="primary">
                                { __( 'Create Studyset', 'wp-flashnotes' ) }
                            </Button>
                        </div> 
                        :
                        <div>
                            <Annotation prefix="Study set attached: ">
                                <p>
                                    {__('A study set is related to this post, to see it, follow the ', 'wp-flashnotes' )} 
                                    <a href={record.link || '#'} target="_blank" >{__('link to its page', 'wp-flashnotes')}</a>
                                </p>
                                <p>
                                    {__('Or you can sync the flashnotes blocks in this post to the study set by clicking on the button below.', 'wp-flshnotes')}
                                </p>
                            </Annotation>
                            <Button variant="secondary">
                                { syncBtnText }
                            </Button>
                        </div>
                    }
                </PanelBody>
            </PluginSidebar>
        </>
    );
    }

registerPlugin( 'wp-flashnotes-editor-sidebar', {
    render: FlashNotesSidebar,
} );
