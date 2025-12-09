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
	let out = item.question 
	? `<div class="role-question">${item.question}</div>`
	: '';
	try {
		const answers = JSON.parse( item.answers || '[]' );
		for ( const ans of answers ) {
			out += ans ? `<div class="role-answer">${ ans }</div>` : '';
		}
	} catch {
		/* ignore */
	}
	out += item.explanation ? `<div class="role-explanation">${item.explanation}</div>` : '';
	return out;
};
