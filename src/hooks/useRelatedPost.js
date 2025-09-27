import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useFetch } from '@wpfn/hooks';

const useRelatedPost = ( { postType, postId } ) => {
	const [ slug, setSlug ] = useState( 'sets/by-set-post-id' );
	const [ targetPostType, setTargetPostType ] = useState( 'post' );
	const [ relatedId, setRelatedId ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		if ( postType !== 'studyset' ) {
			setSlug( 'sets/by-post-id' );
			setTargetPostType( 'studyset' );
		}
	}, [ postType ] );

	const { data, loading: loadingRel, error } = useFetch( `${ slug }/${ postId }` );

	useEffect( () => {
		if ( data?.item?.set_post_id ) {
			setRelatedId( data.item.set_post_id );
		}
	}, [ data ] );

	const record = useSelect(
		( select ) =>
			relatedId
				? select( 'core' ).getEntityRecord( 'postType', targetPostType, relatedId )
				: null,
		[ relatedId, targetPostType ]
	);

	useEffect( () => {
		if ( record !== null ) {
			setLoading( false );
		}
	}, [ record ] );

	return { record, loading: loading || loadingRel, error };
};

export default useRelatedPost;
