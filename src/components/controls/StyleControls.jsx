import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, BorderBoxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function StyleControls( { attributes, setAttributes } ) {
	const { border, backgroundColor } = attributes;

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Styles', 'wp-flashnotes' ) }
				initialOpen={ false }
			>
				<BorderBoxControl
					__next40pxDefaultSize
					label={ __( 'Border', 'wp-flashnotes' ) }
					onChange={ ( val ) =>
						setAttributes( { border: { ...border, ...val } } )
					}
					value={ border }
					enableStyle={ false }
				/>
				<PanelColorSettings
					title={ __( 'Colors', 'wp-flashnotes' ) }
					colorSettings={ [
						{
							value: backgroundColor,
							onChange: ( value ) =>
								setAttributes( { backgroundColor: value } ),
							label: __( 'Background color', 'wp-flashnotes' ),
						},
					] }
				/>
			</PanelBody>
		</InspectorControls>
	);
}
