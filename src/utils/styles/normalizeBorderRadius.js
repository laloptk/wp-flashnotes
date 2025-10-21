const SIDES_MAP = {
	top: 'borderTopRightRadius',
	right: 'borderBottomRightRadius',
	bottom: 'borderBottomLeftRadius',
	left: 'borderTopLeftRadius',
};

const normalizeBorderRadius = ( borderRadius = {} ) => {
	if ( borderRadius ) {
		const individualBorderRadius = {};
		Object.entries( borderRadius ).forEach( ( [ side, value ] ) => {
			individualBorderRadius[ SIDES_MAP[ side ] ] = value;
		} );

		return individualBorderRadius;
	}

	return null;
};

export default normalizeBorderRadius;
