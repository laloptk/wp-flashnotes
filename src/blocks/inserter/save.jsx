import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { id, block_id } = attributes;

	return (
		<div
			{ ...useBlockProps.save({
				className: 'wpfn-card',
			}) }
			data-id={ id || '' }
			data-block-id={ block_id || '' }
		/>
	);
}
