import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	__experimentalBoxControl as BoxControl,
	BorderBoxControl,
} from '@wordpress/components';
import { v4 as uuidv4 } from 'uuid';
import { normalizeText } from '../../utils';
import VisibilityControls from '../../components/controls/VisibilityControls';
import SpacingControls from '../../components/controls/SpacingControls';
import StyleControls from '../../components/controls/StyleControls';
import { __ } from '@wordpress/i18n';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { block_id, margin, padding, border, backgroundColor, hidden } =
		attributes;

	const blockProps = useBlockProps( {
		className: 'wpfn-card',
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

	// Assign UUID once
	useEffect( () => {
		if ( ! block_id ) {
			setAttributes( { block_id: uuidv4() } );
		}
	}, [ block_id, setAttributes ] );

	// Observe direct children (slots)
	const childBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	useEffect( () => {
		if ( ! childBlocks.length ) {
			return;
		}

		let nextQuestion = '';
		let nextAnswers = [];
		let nextExplanation = '';

		childBlocks.forEach( ( child ) => {
			if ( child.name === 'wpfn/slot' ) {
				const role = child.attributes.role;
				const raw = child.attributes.content || '';

				if ( role === 'question' ) {
					nextQuestion = normalizeText( raw );
				} else if ( role === 'answer' ) {
					nextAnswers = raw ? [ normalizeText( raw ) ] : [];
				} else if ( role === 'explanation' ) {
					nextExplanation = raw;
				}
			}
		} );

		if (
			nextQuestion !== attributes.question ||
			JSON.stringify( nextAnswers ) !==
				JSON.stringify( attributes.answers_json ) ||
			nextExplanation !== attributes.explanation
		) {
			setAttributes( {
				question: nextQuestion,
				answers_json: nextAnswers,
				explanation: nextExplanation,
			} );
		}
	}, [
		childBlocks,
		setAttributes,
		attributes.question,
		attributes.answers_json,
		attributes.explanation,
	] );

	return (
		<>
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
				setAttibutes={ setAttributes }
			/>

			<div { ...blockProps }>
				<InnerBlocks
					template={ [
						[ 'wpfn/slot', { role: 'question' } ],
						[
							'wpfn/slot',
							{ role: 'answer', templateLock: 'all' },
						],
						[ 'wpfn/slot', { role: 'explanation' } ],
					] }
					templateLock="all"
					allowedBlocks={ [ 'wpfn/slot' ] }
				/>
			</div>
		</>
	);
}
