import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { id, block_id, object_type } = attributes;

	return (
		<div
			{ ...useBlockProps.save( {
				className: `wpfn-${ object_type }`,
			} ) }
			data-id={ id || '' }
			data-block-id={ block_id || '' }
		/>
	);
}
