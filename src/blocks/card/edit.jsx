import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { v4 as uuidv4 } from 'uuid';
import { normalizeText } from '../../utils';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { block_id } = attributes;
	const blockProps = useBlockProps( { className: 'wpfn-card' } );

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
					// Store plain text only
					nextQuestion = normalizeText( raw );
				} else if ( role === 'answer' ) {
					nextAnswers = raw ? [ normalizeText( raw ) ] : [];
				} else if ( role === 'explanation' ) {
					// Keep HTML for explanation
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
		<div { ...blockProps }>
			<InnerBlocks
				template={ [
					[ 'wpfn/slot', { role: 'question' } ],
					[ 'wpfn/slot', { role: 'answer', templateLock: 'all' } ],
					[ 'wpfn/slot', { role: 'explanation' } ],
				] }
				templateLock="all"
				allowedBlocks={ [ 'wpfn/slot' ] }
			/>
		</div>
	);
}
