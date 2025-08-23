import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

console.log(metadata);

registerBlockType(metadata.name, {
  edit: Edit,
  save: () => null,
});
