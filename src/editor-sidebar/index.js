import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

function FlashNotesSidebar() {
    const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );
    
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
                    <div>
                        <Button primary>
                            { __( 'Create Studyset', 'wp-flashnotes' ) }
                        </Button>
                    </div>
                    <div>
                        <Button secondary>
                            { __( 'Sync Studyset', 'wp-flashnotes' ) }
                        </Button>
                    </div>
                </PanelBody>
            </PluginSidebar>
        </>
    );
    }

registerPlugin( 'wp-flashnotes-editor-sidebar', {
  render: FlashNotesSidebar,
} );

dispatch( 'core/edit-post' ).openGeneralSidebar( 'wp-flashnotes-sidebar' );
