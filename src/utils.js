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
