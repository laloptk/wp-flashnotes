import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { normalizeStyle } from '@wpfn/styles';

export default function save( { attributes } ) {
	const {
		block_id,
		stage,
		border,
		margin,
		padding,
		borderRadius,
		backgroundColor,
	} = attributes;

	const style = {
		...( backgroundColor && { backgroundColor } ),
		...( normalizeStyle( 'border', border ) || {} ),
		...( normalizeStyle( 'margin', margin ) || {} ),
		...( normalizeStyle( 'padding', padding ) || {} ),
		...( normalizeStyle( 'borderRadius', borderRadius ) || {} ),
	};

	const blockProps = useBlockProps.save( {
		className: 'wpfn-card',
		'data-id': block_id,
		'data-stage': stage,
		style,
	} );

	return (
		<div { ...blockProps }>
			<div className="wpfn-slot role-title">
				<InnerBlocks.Content />
			</div>
			<div className="wpfn-slot role-content">
				{ /* answer InnerBlocks */ }
			</div>
		</div>
	);
}
