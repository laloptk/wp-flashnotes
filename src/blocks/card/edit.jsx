import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { normalizeStyle } from '@wpfn/styles';
import { Button } from '@wordpress/components';
import { v4 as uuidv4 } from 'uuid';
import { normalizeText } from '../../utils';
import {
	VisibilityControls,
	SpacingControls,
	StyleControls
} from '@wpfn/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { block_id, margin, padding, border, borderRadius, backgroundColor, hidden } = attributes;
	const [ stage, setStage ] = useState( 0 );
	const nextStage = () => setStage( ( stage + 1 ) % 3 );
	
	const style = {
		...(backgroundColor && { backgroundColor }),
		...(normalizeStyle('border', border) || {}),
		...(normalizeStyle('margin', margin) || {}),
		...(normalizeStyle('padding', padding) || {}),
		...(normalizeStyle('borderRadius', borderRadius) || {}),
	};

	const blockProps = useBlockProps({
		className: 'wpfn-card',
		style
	});

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
				setAttributes={ setAttributes }
			/>

			<div { ...blockProps } data-stage={ stage }>
				<InnerBlocks
					template={ [
						[ 'wpfn/slot', { role: 'question' } ],
						[ 'wpfn/slot', { role: 'answer' } ],
						[ 'wpfn/slot', { role: 'explanation' } ],
					] }
					allowedBlocks={ [ 'wpfn/slot' ] }
					templateLock="all"
				/>
				<Button onClick={ nextStage }>
					{ stage === 0 ? 'Show Answer' : stage === 1 ? 'Show Explanation' : 'Back to Question' }
				</Button>
			</div>
		</>
	);
}
