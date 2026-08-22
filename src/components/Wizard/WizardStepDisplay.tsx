import React from 'react';
import { useTableStore } from '@/store/tableStore';
import { DisplayPanel } from '@/components/Table/panels/DisplayPanel';
import LivePreview from '@/components/Table/LivePreview';

/* =============================================================================
 * WizardStepDisplay
 * =============================================================================
 * Step 3: Display configuration (Colors, Layout & Spacing, Typography)
 * with live preview side by side.
 * ============================================================================= */

const WizardStepDisplay: React.FC = () => {
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
		<div className="w-full grid grid-cols-1 lg:grid-cols-5 gap-6">
			{/* Left: Display Config */}
			<div className="lg:col-span-3">
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
			</div>

			{/* Right: Live Preview */}
			<div className="lg:col-span-2 min-w-0">
				<LivePreview className="sticky top-4" />
			</div>
		</div>
	);
};

export default WizardStepDisplay;
