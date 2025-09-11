import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	__experimentalBoxControl as BoxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SpacingControls( { attributes, setAttributes } ) {
	const { margin, padding } = attributes;

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Spacing', 'wp-flashnotes' ) }
				initialOpen={ false }
			>
				<BoxControl
					label={ __( 'Margin', 'wp-flashnotes' ) }
					values={ margin }
					onChange={ ( value ) => setAttributes( { margin: value } ) }
					__next40pxDefaultSize={ true }
				/>
				<BoxControl
					label={ __( 'Padding', 'wp-flashnotes' ) }
					values={ padding }
					onChange={ ( value ) =>
						setAttributes( { padding: value } )
					}
					__next40pxDefaultSize={ true }
				/>
			</PanelBody>
		</InspectorControls>
	);
}
