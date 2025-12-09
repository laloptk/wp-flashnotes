import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { normalizeStyle } from '@wpfn/styles';
import { Button, PanelBody, RadioControl } from '@wordpress/components';
import { v4 as uuidv4 } from 'uuid';
import { normalizeText } from '../../utils';
import {
	VisibilityControls,
	SpacingControls,
	StyleControls,
} from '@wpfn/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const {
		block_id,
		margin,
		padding,
		border,
		borderRadius,
		backgroundColor,
		hidden,
		right_answers,
		card_type,
	} = attributes;
	const [ stage, setStage ] = useState( 0 );
	const [ selectedAnswer, setSelectedAnswer ] = useState( null );
	const nextStage = () => setStage( ( stage + 1 ) % 3 );

	const style = {
		...( backgroundColor && { backgroundColor } ),
		...( normalizeStyle( 'border', border ) || {} ),
		...( normalizeStyle( 'margin', margin ) || {} ),
		...( normalizeStyle( 'padding', padding ) || {} ),
		...( normalizeStyle( 'borderRadius', borderRadius ) || {} ),
	};

	const blockProps = useBlockProps( {
		className: 'wpfn-card wpfn-true-false-question',
		style,
	} );

	// Assign UUID once
	useEffect( () => {
		if ( ! block_id ) {
			setAttributes( { 
				block_id: uuidv4(),
				card_type: "true_false" 
			} );
		}
	}, [ block_id, setAttributes ] );

	// Initialize right_answers if empty
	useEffect( () => {
		if ( ! right_answers || right_answers.length === 0 ) {
			setAttributes( { right_answers: [ 'true' ] } );
		}
	}, [ right_answers, setAttributes ] );

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
		let nextExplanation = '';

		childBlocks.forEach( ( child ) => {
			if ( child.name === 'wpfn/slot' ) {
				const role = child.attributes.role;
				const raw = child.attributes.content || '';

				if ( role === 'question' ) {
					nextQuestion = normalizeText( raw );
				} else if ( role === 'explanation' ) {
					nextExplanation = raw;
				}
			}
		} );

		if (
			nextQuestion !== attributes.question ||
			nextExplanation !== attributes.explanation
		) {
			setAttributes( {
				question: nextQuestion,
				explanation: nextExplanation,
			} );
		}
	}, [
		childBlocks,
		setAttributes,
		attributes.question,
		attributes.explanation,
	] );

	const handleAnswerClick = ( answer ) => {
		setSelectedAnswer( answer );
		setStage( 1 ); // Move to answer stage
	};

	// Get the correct answer (first item in array for true/false)
	const correctAnswer = right_answers && right_answers.length > 0 
		? right_answers[ 0 ] 
		: 'true';
	
	const isCorrect = selectedAnswer === correctAnswer;

	// Handle radio control change - update array with single value
	const handleCorrectAnswerChange = ( value ) => {
		setAttributes( { right_answers: [ value ] } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Question Settings', 'wpfn' ) }>
					<RadioControl
						label={ __( 'Correct Answer', 'wpfn' ) }
						selected={ correctAnswer }
						options={ [
							{ label: __( 'True', 'wpfn' ), value: 'true' },
							{ label: __( 'False', 'wpfn' ), value: 'false' },
						] }
						onChange={ handleCorrectAnswerChange }
					/>
				</PanelBody>
			</InspectorControls>

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
						[ 'wpfn/slot', { role: 'explanation' } ],
					] }
					allowedBlocks={ [ 'wpfn/slot' ] }
					templateLock="all"
				/>

				{ stage === 0 && (
					<div className="wpfn-true-false-options">
						<button
							className="wpfn-option-button wpfn-true-button"
							onClick={ () => handleAnswerClick( 'true' ) }
							type="button"
						>
							{ __( 'True', 'wpfn' ) }
						</button>
						<button
							className="wpfn-option-button wpfn-false-button"
							onClick={ () => handleAnswerClick( 'false' ) }
							type="button"
						>
							{ __( 'False', 'wpfn' ) }
						</button>
					</div>
				) }

				{ stage === 1 && (
					<div
						className={ `wpfn-answer-feedback ${ 
							isCorrect ? 'wpfn-correct' : 'wpfn-incorrect'
						}` }
					>
						<p>
							{ isCorrect
								? __( 'Correct!', 'wpfn' )
								: __( 'Incorrect!', 'wpfn' ) }
						</p>
						<p>
							{ __( 'The correct answer is: ', 'wpfn' ) }
							<strong>
								{ correctAnswer === 'true'
									? __( 'True', 'wpfn' )
									: __( 'False', 'wpfn' ) }
							</strong>
						</p>
					</div>
				) }

				<Button onClick={ nextStage }>
					{ stage === 0
						? __( 'Show Answer', 'wpfn' )
						: stage === 1
						? __( 'Show Explanation', 'wpfn' )
						: __( 'Back to Question', 'wpfn' ) }
				</Button>
			</div>
		</>
	);
}