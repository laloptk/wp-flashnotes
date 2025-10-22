import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useFetch } from '@wpfn/hooks';

const useRelatedPost = ( { postType, postId } ) => {
	const [ slug, setSlug ] = useState( 'sets/by-set-post-id' );
	const [ relatedIds, setRelatedIds ] = useState( {
		studysetId: null,
		originPostId: null,
	} );

	useEffect( () => {
		setSlug(
			postType === 'studyset' ? 'sets/by-set-post-id' : 'sets/by-post-id'
		);
	}, [ postType ] );

	const {
		data: relationship,
		loading: loadingRel,
		error,
	} = useFetch( `${ slug }/${ postId }` );

	useEffect( () => {
		setRelatedIds( {
			studysetId: relationship?.item?.set_post_id ?? null,
			originPostId: relationship?.item?.post_id ?? null,
		} );
	}, [ relationship ] );

	const sameId =
		relatedIds.studysetId !== null &&
		relatedIds.studysetId === relatedIds.originPostId;

	const derivedOriginType = sameId ? 'studyset' : postType;

	const records = useSelect(
		( select ) => {
			const core = select( 'core' );

			if ( sameId ) {
				const record = relatedIds.studysetId
					? core.getEntityRecord(
							'postType',
							'studyset',
							relatedIds.studysetId
					  )
					: null;

				return { studysetRecord: record, originPostRecord: record };
			}

			return {
				studysetRecord: relatedIds.studysetId
					? core.getEntityRecord(
							'postType',
							'studyset',
							relatedIds.studysetId
					  )
					: null,
				originPostRecord: relatedIds.originPostId
					? core.getEntityRecord(
							'postType',
							derivedOriginType,
							relatedIds.originPostId
					  )
					: null,
			};
		},
		[
			sameId,
			relatedIds.studysetId,
			relatedIds.originPostId,
			derivedOriginType,
		]
	);

	// ✅ Corrected loading state: only depend on REST fetch
	const loading = loadingRel;

	return { records, relationship, loading, error };
};

export default useRelatedPost;
