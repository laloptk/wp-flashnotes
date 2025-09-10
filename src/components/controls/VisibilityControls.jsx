import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function VisibilityControls( { attributes, setAttributes } ) {
	const { hidden } = attributes;

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Visibility', 'wp-flashnotes' ) } initialOpen={ true }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Hide on frontend', 'wp-flashnotes' ) }
					checked={ hidden }
					onChange={ ( value ) => setAttributes( { hidden: value } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);
}
