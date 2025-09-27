import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, Spinner } from '@wordpress/components';
import { Annotation } from '@wpfn/components';
import { __ } from '@wordpress/i18n';
import { dispatch, useSelect } from '@wordpress/data';
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useRelatedPost } from '@wpfn/hooks';
import apiFetch from '@wordpress/api-fetch';

function FlashNotesSidebar() {
    const [syncBtnText, setSyncBtnText] = useState('Sync study set');
    
    const { postId, postType, title, content, author } = useSelect( ( select ) => {
        const editor = select( 'core/editor' );
            return {
                postId: editor.getCurrentPostId(),
                postType: editor.getCurrentPostType(),
                title: editor.getEditedPostAttribute( 'title' ),
                content: editor.getEditedPostContent(),
                author: editor.getEditedPostAttribute( 'author' ),
            };
    }, [] );
    
    useEffect(() => {
        if(postType === 'studyset') {
            setSyncBtnText('Sync from post');
        } 
    }, []);

    const { record, loading, error } = useRelatedPost( { postType, postId } );

    const handleSetUpsert = useCallback(
        ( type ) => {
            const path = `/wp/v2/studyset${
                record?.id && type === 'update' ? '/' + record.id : ''
            }`;

            const data = { content };

            let method = 'PUT';

            if ( type === 'insert' ) {
                method = 'POST';
                data.title = title;
                data.author = author;
                data.status = 'publish';
            }

            apiFetch( { path, method, data } ).then( ( response ) => {
                console.log(
                    type === 'insert' ? 'Studyset created' : 'Studyset updated',
                    response
                );
            } );
        },
        [ record?.id, title, author, content ]
    );
    
    useEffect( () => {
        if ( postType === 'post' || postType === 'studyset' ) {
            dispatch( 'core/edit-post' ).openGeneralSidebar( 'wp-flashnotes-sidebar' );
        }
    }, [ postType ] );
    
    return (
        <>
            <PluginSidebarMoreMenuItem target="wp-flashnotes-sidebar">
                { __( 'WP FlashNotes', 'wp-flashnotes' ) }
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="wp-flashnotes-sidebar"
                title={ __( 'FlashNotes', 'wp-flashnotes' ) }
                icon="edit"  /* optional icon slug */
            >
                <PanelBody>
                    {(! record && postType !== 'studyset')
                        ?
                        <div>
                            <Annotation prefix={__("Study set not attached: ")} >
                                <p>
                                    {__('This post does not have a study set linked to it.', 'wp-flashnotes' )} 
                                    {__('To generate one, click on the button below.', 'wp-flashnotes')}
                                </p>
                            </Annotation>
                            <Button variant="primary" onClick={() => handleSetUpsert('insert')}>
                                { __( 'Create Studyset', 'wp-flashnotes' ) }
                            </Button>
                        </div> 
                        :
                        <div>
                            <Annotation prefix="Study set attached: ">
                                <p>
                                    {__('A study set is related to this post, to see it, follow the ', 'wp-flashnotes' )} 
                                    <a href={record?.link || '#'} target="_blank" >{__('link to its page', 'wp-flashnotes')}</a>
                                </p>
                                <p>
                                    {__('Or you can sync the flashnotes blocks in this post to the study set by clicking on the button below.', 'wp-flshnotes')}
                                </p>
                            </Annotation>
                            <Button 
                                variant="secondary"
                                onClick={() => handleSetUpsert('update')}
                            >
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
