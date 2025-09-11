import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import CardsNotesSearch from '../../components/CardsNotesSearch';
import VisibilityControls from '../../components/controls/VisibilityControls';
import SpacingControls from '../../components/controls/SpacingControls';
import StyleControls from '../../components/controls/StyleControls';

const Edit = ( { attributes, setAttributes, clientId } ) => {
	const {
		id,
		title,
		answers_json,
		explanation,
		margin,
		padding,
		border,
		backgroundColor,
		hidden,
	} = attributes;

	// Get the replaceInnerBlocks function from dispatch
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	const blockProps = useBlockProps( {
		className: 'wpfn-inserter',
		style: {
			marginTop: margin?.top,
			marginRight: margin?.right,
			marginBottom: margin?.bottom,
			marginLeft: margin?.left,
			paddingTop: padding?.top,
			paddingRight: padding?.right,
			paddingBottom: padding?.bottom,
			paddingLeft: padding?.left,
			borderTop: border?.top?.width
				? `${ border.top.width } solid ${ border.top.color || '#ddd' }`
				: undefined,
			borderRight: border?.right?.width
				? `${ border.right.width } solid ${
						border.right.color || '#ddd'
				  }`
				: undefined,
			borderBottom: border?.bottom?.width
				? `${ border.bottom.width } solid ${
						border.bottom.color || '#ddd'
				  }`
				: undefined,
			borderLeft: border?.left?.width
				? `${ border.left.width } solid ${
						border.left.color || '#ddd'
				  }`
				: undefined,
			borderRadius: border?.radius,
			backgroundColor,
		},
	} );

	// Function to safely parse block content
	const parseBlockContent = ( content ) => {
		if ( ! content || typeof content !== 'string' ) {
			return [];
		}
		
		try {
			// Parse the Gutenberg markup into blocks
			return parse( content );
		} catch ( error ) {
			console.error( 'Error parsing block content:', error );
			return [];
		}
	};

	// Function to create all blocks from attributes
	const createBlocksFromAttributes = () => {
		const allBlocks = [];

		// Parse title blocks
		if ( title ) {
			const titleBlocks = parseBlockContent( title );
			allBlocks.push( ...titleBlocks );
		}

		// Parse answer blocks
		if ( answers_json && Array.isArray( answers_json ) ) {
			answers_json.forEach( ( answer ) => {
				if ( answer && typeof answer === 'string' ) {
					const answerBlocks = parseBlockContent( answer );
					allBlocks.push( ...answerBlocks );
				}
			} );
		}

		// Parse explanation blocks
		if ( explanation ) {
			const explanationBlocks = parseBlockContent( explanation );
			allBlocks.push( ...explanationBlocks );
		}

		// If no blocks were parsed, add a default placeholder
		if ( allBlocks.length === 0 ) {
			allBlocks.push( ...parse( '<!-- wp:paragraph --><p>Insert note or card here</p><!-- /wp:paragraph -->' ) );
		}

		return allBlocks;
	};

	// Replace inner blocks when attributes change
	useEffect( () => {
		const newBlocks = createBlocksFromAttributes();
		
		if ( newBlocks.length > 0 ) {
			replaceInnerBlocks( clientId, newBlocks );
		}
	}, [ title, answers_json, explanation, clientId, replaceInnerBlocks ] );

	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{ className: 'wpfn-inserter' }, 
		{
			// No template needed since we're replacing blocks programmatically
			templateLock: 'all',
		}
	);

	const handleSearchOnChange = ( selectedItem ) => {
		// Here answers_json should contain block markup strings
		let answers = [];
		try {
			if ( selectedItem?.answers_json ) {
				answers = JSON.parse( selectedItem.answers_json );
			}
		} catch (e) {
			console.error('Error parsing answers_json:', e);
			answers = [];
		}

		setAttributes( {
			id: selectedItem?.id ?? '',
			title: selectedItem?.question ?? selectedItem?.title ?? '',
			answers_json: answers,
			explanation: selectedItem?.explanation ?? '',
		} );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Card Controls', 'wp-flashnotes' ) }>
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

			<div { ...innerBlocksProps }>
				{ children }
			</div>
		</div>
	);
};

export default Edit;