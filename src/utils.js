import { parse } from '@wordpress/blocks';

export const normalizeText = ( val ) => {
	if ( ! val ) {
		return '';
	}

	if ( typeof val === 'string' ) {
		return val;
	}

	if ( val instanceof Object ) {
		if ( val.text !== undefined ) {
			return val.text;
		}

		if ( typeof val.toString === 'function' ) {
			return val.toString();
		}
	}

	return String( val );
};

export const assembleContent = ( item ) => {
	if ( ! item ) {
		return '';
	}
	let out = item.question || '';
	try {
		const answers = JSON.parse( item.answers_json || '[]' );
		for ( const ans of answers ) {
			out += ans || '';
		}
	} catch {
		/* ignore */
	}
	out += item.explanation || '';
	return out;
};
