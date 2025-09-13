const assembleBorderValues = (border = {}) => {
    const style = border.style || 'solid';
    if (border.color && border.width) {
        return `${border.width} ${style} ${border.color}`;
    }
    return null;
};

const normalizeBorder = (border = {}) => {
    // Case 1: shorthand (one border for all sides)
    if (border.color && border.width) {
        return { border: assembleBorderValues(border) };
    }

    // Case 2: individual borders
    const individualBorders = {};
    Object.entries(border).forEach(([side, value]) => {
        const assembled = assembleBorderValues(value);
        if (assembled) {
            individualBorders[`border${side.charAt(0).toUpperCase() + side.slice(1)}`] = assembled;
        }
    });

    return individualBorders;
};

export default normalizeBorder;