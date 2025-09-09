import DOMPurify from 'isomorphic-dompurify';
import { parse, serialize } from '@wordpress/blocks';

const SafeHTMLContent = ( { content, classes = '' } ) => {
	if ( ! content ) {
		return null;
	}

	let html = content;

	try {
		// Detect if this looks like serialized Gutenberg blocks
		if ( content.includes( '<!-- wp:' ) ) {
			const blocks = parse( content );
			// Re-serialize and strip block comments
			html = serialize( blocks )
				.replace( /<!--.*?-->/gs, '' )
				.trim();
		}
	} catch ( err ) {
		console.error( 'SafeHTMLContent parse error:', err );
	}

	const sanitized = DOMPurify.sanitize( html );

	return (
		<div
			className={ classes }
			dangerouslySetInnerHTML={ { __html: sanitized } }
		/>
	);
};

export default SafeHTMLContent;
