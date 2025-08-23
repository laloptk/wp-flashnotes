import {useState, useEffect} from '@wordpress/element';
import ResourcesAPIService from '../ResourcesAPIService';

const useFetch = (type, query_params = {}) => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    
    useEffect(() => {
        const getItems = async () => {
            try {
                setLoading(true);
                const api = new ResourcesAPIService(type);
                const results = await api.get(query_params);
                setData(results);
            } catch(e) {
                setLoading(false);
                throw new Error(e);
            } finally {
                setLoading(false);
            }
        }

        getItems();
    }, [type]);
}

export default useFetch;