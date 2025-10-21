const Annotation = ( { children, prefix = '' } ) => {
	return (
		<div>
			{ prefix && <h4>{ `${ prefix }` }</h4> }
			{ children }
		</div>
	);
};

export default Annotation;
