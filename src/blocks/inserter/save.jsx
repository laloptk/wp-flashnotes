import { useBlockProps } from '@wordpress/block-editor';
import { normalizeStyle } from '@wpfn/styles';

export default function save( { attributes } ) {
	const { id, block_id, border, margin, padding, borderRadius, backgroundColor } = attributes;
	
	const style = {
		...(backgroundColor && { backgroundColor }),
		...(normalizeStyle('border', border) || {}),
		...(normalizeStyle('margin', margin) || {}),
		...(normalizeStyle('padding', padding) || {}),
		...(normalizeStyle('borderRadius', borderRadius) || {}),
	};

	return (
		<div
			{ ...useBlockProps.save({style}) }
			data-id={ id || '' }
			data-block-id={ block_id || '' }
		/>
	);
}
