import normalizeBorder from "./normalizeBorder";
import normalizeBorderRadius from "./normalizeBorderRadius";
import normalizeSpacing from "./normalizeSpacing";

const styles = {
    border: (border) => normalizeBorder(border),
    margin: (margin) => normalizeSpacing(margin, 'margin'),
    padding: (margin) => normalizeSpacing(margin, 'padding'),
    borderRadius: (borderRadius) => normalizeBorderRadius(borderRadius)
};

const normalizeStyle = (type = null, value = null) => {
    if (type && value && typeof styles[type] === 'function') {
        return styles[type](value);
    }

    return null;
}

export default normalizeStyle;
