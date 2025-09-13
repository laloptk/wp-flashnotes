const normalizeSpacing = (spacing = {}, type) => {
    if(spacing) {
        const individualSpacings = {};
        Object.entries(spacing).forEach(([side, value]) => {
            individualSpacings[`${type}${side.charAt(0).toUpperCase() + side.slice(1)}`] = value;
        });

        return individualSpacings;
    }
    
    return null;
};

export default normalizeSpacing;