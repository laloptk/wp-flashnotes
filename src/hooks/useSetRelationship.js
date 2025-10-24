import { useState, useEffect } from '@wordpress/element';
import ResourcesAPIService from '../ResourcesAPIService';

const useSetRelationship = (postType, postId) => {
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		if (!postId) return;

		const controller = new AbortController();
		const api = new ResourcesAPIService('sets');

		const type =
			postType === 'studyset' ? 'by-set-post-id' : 'by-post-id';

		(async () => {
			try {
				setLoading(true);
				setError(null);
				const res = await api.getSetRelationship(
					{ type, id: postId },
					{ signal: controller.signal }
				);
				setData(res);
			} catch (e) {
				if (e?.name !== 'AbortError') setError(e.message ?? String(e));
			} finally {
				setLoading(false);
			}
		})();

		return () => controller.abort();
	}, [postType, postId]);

	return { data, loading, error };
};

export default useSetRelationship;