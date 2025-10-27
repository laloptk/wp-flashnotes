import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeStyle } from '@wpfn/styles';
import {
    CardsNotesSearch,
    VisibilityControls,
    SpacingControls,
    StyleControls,
    SafeHTMLContent,
} from '@wpfn/components';
import { useFetch } from '@wpfn/hooks';

const Edit = ( { attributes, setAttributes, clientId } ) => {
    const [ content, setContent ] = useState( '' );
    
    const {
        id,
        block_id,
        note_block_id,
        object_type,
        margin,
        padding,
        border,
        borderRadius,
        backgroundColor,
        hidden,
    } = attributes;

    const style = {
        ...( backgroundColor && { backgroundColor } ),
        ...( normalizeStyle( 'border', border ) || {} ),
        ...( normalizeStyle( 'margin', margin ) || {} ),
        ...( normalizeStyle( 'padding', padding ) || {} ),
        ...( normalizeStyle( 'borderRadius', borderRadius ) || {} ),
    };

    const blockProps = useBlockProps( {
        className: 'wpfn-note-inserter',
        style,
    } );

    useEffect( () => {
        if ( ! block_id ) {
            setAttributes( { block_id: clientId } );
        }
    }, [ block_id, clientId, setAttributes ] );

    const query = id ? { id } : { block_id: note_block_id };
    const { data, loading, error } = useFetch( 'notes', query );

    useEffect( () => {
        if ( ! data?.items?.length ) {
            return;
        }

        const item = data.items[ 0 ];
        if ( ! item ) {
            return;
        }
        
        setAttributes( { id: item.id } );
        setContent( `${item.title}${item.content}` );
    }, [ data ] );

    const handleSearchOnChange = ( selectedItem ) => {
        if ( ! selectedItem ) {
            return;
        }
        setAttributes( {
            id: selectedItem.id,
            note_block_id: selectedItem.block_id,
        } );
        setContent( `${selectedItem.title}${selectedItem.content}` );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Search Controls', 'wp-flashnotes' ) }>
                    <CardsNotesSearch
                        itemType="notes"
                        onChange={ handleSearchOnChange }
                    />
                </PanelBody>
                <VisibilityControls
                    attributes={ attributes }
                    setAttributes={ setAttributes }
                />
                <SpacingControls
                    attributes={ attributes }
                    setAttributes={ setAttributes }
                />
                <StyleControls
                    attributes={ attributes }
                    setAttributes={ setAttributes }
                />
            </InspectorControls>
            <div { ...blockProps } >
                <div className="wpfn-rendered-note">
                    { error && <p className="error">{ String( error ) }</p> }
                    { loading && <Spinner /> }
                    { ! loading && ! content && (
                        <p>{ __( 'No content available.', 'wp-flashnotes' ) }</p>
                    ) }
                    { content && (
                        <SafeHTMLContent
                            content={ content }
                            classes="wpfn-note-html"
                        />
                    ) }
                </div>
            </div>
        </>
    );
};

export default Edit;
