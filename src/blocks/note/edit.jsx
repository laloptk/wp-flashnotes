import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { v4 as uuidv4 } from 'uuid';
import { serialize } from '@wordpress/blocks';

const Edit = ( { clientId, allowedBlocks, attributes, setAttributes } ) => {
	const { block_id, title, content, hide } = attributes;
	const blockProps = useBlockProps( { className: 'wpfn-note' } );

	if ( ! block_id ) {
		setAttributes( { block_id: uuidv4() } );
	}

	// Observe this block’s direct children
	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	// Sync inner blocks → `attributes.content` as HTML
	useEffect( () => {
		const html = serialize( innerBlocks ); // produces HTML with comments
		setAttributes( { content: html } );
	}, [ innerBlocks, setAttributes ] );

	return (
		<div { ...blockProps }>
			<TextControl
				label={ __( 'Title', 'wp-flashnotes' ) }
				value={ title }
				onChange={ ( val ) => setAttributes( { title: val } ) }
				className="wpfn-note__title"
			/>

			<div className="wpfn-note__content">
				<InnerBlocks allowedBlocks={ allowedBlocks } />
			</div>

			<ToggleControl
				label={ __( 'Hide Note In Frontend', 'wp-flashnotes' ) }
				checked={ hide }
				onChange={ ( val ) => setAttributes( { hide: val } ) }
			/>
		</div>
	);
};

export default Edit;
