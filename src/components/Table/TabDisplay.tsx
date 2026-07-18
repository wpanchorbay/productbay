import React from 'react';
import { useTableStore } from '@/store/tableStore';
import { DisplayPanel } from '@/components/Table/panels/DisplayPanel';

/* =============================================================================
 * TabDisplay Component
 * =============================================================================
 * Wrapper around DisplayPanel that connects it to the table store.
 * ============================================================================= */

const TabDisplay: React.FC = () => {
	const {
		style,
		setHeaderStyle,
		setBodyStyle,
		setButtonStyle,
		setLayoutStyle,
		setTypographyStyle,
		setHoverStyle,
		setResponsiveStyle,
	} = useTableStore();

	return (
		<DisplayPanel
			style={style}
			setHeaderStyle={setHeaderStyle}
			setBodyStyle={setBodyStyle}
			setButtonStyle={setButtonStyle}
			setLayoutStyle={setLayoutStyle}
			setTypographyStyle={setTypographyStyle}
			setHoverStyle={setHoverStyle}
			setResponsiveStyle={setResponsiveStyle}
		/>
	);
};

export default TabDisplay;
