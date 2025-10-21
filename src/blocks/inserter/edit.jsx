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
import { assembleContent } from '../../utils';

const Edit = ( { attributes, setAttributes, clientId } ) => {
	const {
		id,
		block_id,
		card_block_id,
		margin,
		padding,
		border,
		borderRadius,
		backgroundColor,
		hidden,
	} = attributes;
	console.log( attributes );
	const style = {
		...( backgroundColor && { backgroundColor } ),
		...( normalizeStyle( 'border', border ) || {} ),
		...( normalizeStyle( 'margin', margin ) || {} ),
		...( normalizeStyle( 'padding', padding ) || {} ),
		...( normalizeStyle( 'borderRadius', borderRadius ) || {} ),
	};

	const blockProps = useBlockProps( {
		className: 'wpfn-inserter',
		style,
	} );

	useEffect( () => {
		if ( ! block_id ) {
			setAttributes( { block_id: clientId } );
		}
	}, [ block_id, clientId, setAttributes ] );

	const [ content, setContent ] = useState( '' );

	const query = id ? { id } : { block_id: card_block_id };
	const { data, loading, error } = useFetch( 'cards', query );

	console.log( data );

	useEffect( () => {
		if ( ! data?.items?.length ) {
			return;
		}

		const item = data.items[ 0 ];
		if ( ! item ) {
			return;
		}

		setAttributes( { id: item.id } );
		setContent( assembleContent( item ) );
	}, [ data ] );

	const handleSearchOnChange = ( selectedItem ) => {
		if ( ! selectedItem ) {
			return;
		}
		setAttributes( {
			id: selectedItem.id,
			card_block_id: selectedItem.block_id,
		} );
		setContent( assembleContent( selectedItem ) );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Search Controls', 'wp-flashnotes' ) }>
					<CardsNotesSearch
						itemType="cards"
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

			<div className="wpfn-rendered-card">
				{ error && <p className="error">{ String( error ) }</p> }
				{ loading && <Spinner /> }
				{ ! loading && ! content && (
					<p>{ __( 'No content available.', 'wp-flashnotes' ) }</p>
				) }
				{ content && (
					<SafeHTMLContent
						content={ content }
						classes="wpfn-card-html"
					/>
				) }
			</div>
		</div>
	);
};

export default Edit;
