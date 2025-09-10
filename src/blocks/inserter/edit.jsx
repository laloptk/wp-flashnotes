import CardsNotesSearch from '../../components/CardsNotesSearch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ColorPalette } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SafeHTMLContent from '../../components/SafeHTMLContent';
import VisibilityControls from '../../components/controls/VisibilityControls';
import SpacingControls from '../../components/controls/SpacingControls';
import StyleControls from '../../components/controls/StyleControls';

const Edit = ( { attributes, setAttributes } ) => {
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
				? `${ border.right.width } solid ${ border.right.color || '#ddd' }`
				: undefined,
			borderBottom: border?.bottom?.width
				? `${ border.bottom.width } solid ${ border.bottom.color || '#ddd' }`
				: undefined,
			borderLeft: border?.left?.width
				? `${ border.left.width } solid ${ border.left.color || '#ddd' }`
				: undefined,
			borderRadius: border?.radius,
			backgroundColor,
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
			</InspectorControls>
			<VisibilityControls attributes={attributes} setAttributes={setAttributes} />
			<SpacingControls attributes={attributes} setAttributes={setAttributes} />
			<StyleControls attributes={attributes} setAttributes={setAttributes} />
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
