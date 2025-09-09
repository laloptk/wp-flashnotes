import CardsNotesSearch from '../../components/CardsNotesSearch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ColorPalette } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SafeHTMLContent from '../../components/SafeHTMLContent';

const Edit = ( { attributes, setAttributes } ) => {
	const {
		id,
		title,
		answers_json,
		explanation,
		borderRadius,
		shadow,
		borderColor,
		backgroundColor,
		padding,
		margin,
	} = attributes;

	const blockProps = useBlockProps( {
		style: {
			borderRadius: `${ borderRadius }px`,
			boxShadow: shadow
				? `0 ${ shadow }px ${ 2 * shadow }px rgba(0,0,0,0.1)`
				: 'none',
			border: `1px solid ${ borderColor }`,
			backgroundColor,
			padding: `${ padding }px`,
			margin: `${ margin }px`,
		},
	} );

	const handleSearchOnChange = ( selectedItem ) => {
		const answer = JSON.parse( selectedItem?.answers_json ) ?? '';
		setAttributes( {
			id: selectedItem?.id ?? '',
			title: selectedItem?.question ?? selectedItem?.title ?? '',
			answers_json: answer ?? '',
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
				<PanelBody
					title={ __( 'Card Style', 'wp-flashnotes' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Border Radius', 'wp-flashnotes' ) }
						value={ borderRadius }
						onChange={ ( value ) =>
							setAttributes( { borderRadius: value } )
						}
						min={ 0 }
						max={ 24 }
					/>
					<RangeControl
						label={ __( 'Shadow Strength', 'wp-flashnotes' ) }
						value={ shadow }
						onChange={ ( value ) =>
							setAttributes( { shadow: value } )
						}
						min={ 0 }
						max={ 10 }
					/>
					<p>{ __( 'Border Color', 'wp-flashnotes' ) }</p>
					<ColorPalette
						value={ borderColor }
						onChange={ ( value ) =>
							setAttributes( { borderColor: value } )
						}
					/>
					<p>{ __( 'Background Color', 'wp-flashnotes' ) }</p>
					<ColorPalette
						value={ backgroundColor }
						onChange={ ( value ) =>
							setAttributes( { backgroundColor: value } )
						}
					/>
					<RangeControl
						label={ __( 'Padding', 'wp-flashnotes' ) }
						value={ padding }
						onChange={ ( value ) =>
							setAttributes( { padding: value } )
						}
						min={ 0 }
						max={ 64 }
					/>
					<RangeControl
						label={ __( 'Margin', 'wp-flashnotes' ) }
						value={ margin }
						onChange={ ( value ) =>
							setAttributes( { margin: value } )
						}
						min={ 0 }
						max={ 64 }
					/>
				</PanelBody>
			</InspectorControls>
			<div>
				{ title && (
					<SafeHTMLContent
						content={ title }
						classes="wpfn-card-title"
					/>
				) }
				{ answers_json?.[ 0 ] && (
					<SafeHTMLContent
						content={ answers_json?.[ 0 ] }
						classes="wpfn-card-answer"
					/>
				) }
				{ explanation && (
					<SafeHTMLContent
						content={ explanation }
						classes="wpfn-card-explanation"
					/>
				) }
				{ ! title && ! answers_json && ! explanation && (
					<>
						<h3>
							{ __( 'Question placeholder…', 'wp-flashnotes' ) }
						</h3>
						<div>
							{ __( 'Answer placeholder…', 'wp-flashnotes' ) }
						</div>
						<div>
							{ __(
								'Explanation placeholder…',
								'wp-flashnotes'
							) }
						</div>
					</>
				) }
			</div>
		</div>
	);
};

export default Edit;
