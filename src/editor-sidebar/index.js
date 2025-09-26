import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, Spinner } from '@wordpress/components';
import { Annotation } from '@wpfn/components';
import { __ } from '@wordpress/i18n';
import { dispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { useFetch } from '@wpfn/hooks';

function FlashNotesSidebar() {
    const [fetchSlug, setFetchSlug] = useState('sets/by-post-id');
    const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );
    
    useEffect(() => {
        if(postType === 'studyset') {
            setFetchSlug('sets/by-set-post-id');
        } 
    }, []);
    // Fetch for relationship between post and studyset (sets table endpoint)
    const { data, loading, error } = useFetch(`${fetchSlug}/${postId}`);
    console.log(data);
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
                    {(! data)
                        ?
                        <div>
                            <Button variant="primary">
                                { __( 'Create Studyset', 'wp-flashnotes' ) }
                            </Button>
                        </div> 
                        :
                        <div>
                            <Annotation prefix="Study set attached: ">
                                <p>
                                    A study set is related to this post, 
                                    to see it, follow the <a href="" >link to its page</a>.
                                </p>
                                <p>
                                    Or you can sync the flashnotes blocks in this post to 
                                    the study set by clicking on the button below.
                                </p>
                            </Annotation>
                            <Button variant="secondary">
                                { __( 'Sync Studyset', 'wp-flashnotes' ) }
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

dispatch( 'core/edit-post' ).openGeneralSidebar( 'wp-flashnotes-sidebar' );
