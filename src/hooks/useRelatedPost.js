import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useSetRelationship } from '@wpfn/hooks';

const useRelatedPost = ( { postType, postId } ) => {
	const [ relatedIds, setRelatedIds ] = useState( {
		studysetId: null,
		originPostId: null,
	} );

	const {
		data: relationship,
		loading,
		error,
	} = useSetRelationship( postType, postId );

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

	return { records, relationship, loading, error };
};

export default useRelatedPost;
