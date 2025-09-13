import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { 
	PanelBody, 
	BorderBoxControl,
	BoxControl
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const StyleControls = ( { attributes, setAttributes } ) => {
	const { border, borderRadius, backgroundColor } = attributes;

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
						setAttributes( { border: { ...val } } )
					}
					value={ border }
					enableStyle={ false }
				/>
				<BoxControl
					__next40pxDefaultSize
					label={ __( 'Border Radius', 'wp-flashnotes' ) }
					onChange={ ( val ) =>
						setAttributes( { borderRadius: { ...val } } )
					}
					value={ borderRadius }
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

export default StyleControls;
