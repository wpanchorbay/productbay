import { cn } from '@/utils/cn';
import { __ } from '@wordpress/i18n';
import { Toggle } from '@/components/ui/Toggle';
import { Select } from '@/components/ui/Select';
import { ColorPicker } from '@/components/ui/ColorPicker';
import SectionHeading from '@/components/Table/SectionHeading';
import { SettingsOption } from '@/components/Table/SettingsOption';

import { TableStyle } from '@/types';

/* =============================================================================
 * ColorChoice Component (Internal)
 * Standardizes the 2-column color picker layout (Background + Text).
 * ============================================================================= */
interface ColorChoiceProps {
	bgColor: string;
	labelBg: string;
	labelColor: string;
	textColor: string;
	className?: string;
	onBgChange: (val: string) => void;
	onColorChange: (val: string) => void;
}

const ColorChoice = ({
	labelBg,
	labelColor,
	bgColor,
	textColor,
	onBgChange,
	onColorChange,
	className,
}: ColorChoiceProps) => (
	<div className={cn('grid grid-cols-1 lg:grid-cols-2 gap-4 items-center', className)}>
		<div className="flex items-center justify-between lg:gap-8 border border-gray-200 p-2 bg-white">
			<span className="text-sm text-gray-600">{labelBg}</span>
			<ColorPicker value={bgColor} onChange={onBgChange} triggerMode="icon" />
		</div>
		<div className="flex items-center justify-between lg:gap-8 border border-gray-200 p-2 bg-white">
			<span className="text-sm text-gray-600">{labelColor}</span>
			<ColorPicker value={textColor} onChange={onColorChange} triggerMode="icon" />
		</div>
	</div>
);

/* =============================================================================
 * DisplayPanel Component
 * =============================================================================
 * Reusable panel for configuring table display settings.
 * ============================================================================= */

export interface DisplayPanelProps {
	style: TableStyle;
	setHeaderStyle: (header: Partial<TableStyle['header']>) => void;
	setBodyStyle: (body: Partial<TableStyle['body']>) => void;
	setButtonStyle: (button: Partial<TableStyle['button']>) => void;
	setLayoutStyle: (layout: Partial<TableStyle['layout']>) => void;
	setTypographyStyle: (typography: Partial<TableStyle['typography']>) => void;
	setHoverStyle: (hover: Partial<TableStyle['hover']>) => void;
	setResponsiveStyle: (responsive: Partial<TableStyle['responsive']>) => void;

	className?: string;
}

export const DisplayPanel = ({
	style,
	setHeaderStyle,
	setBodyStyle,
	setButtonStyle,
	setLayoutStyle,
	setTypographyStyle,
	setHoverStyle,
	setResponsiveStyle,

	className,
}: DisplayPanelProps) => {
	return (
		<div className={cn('w-full p-4 space-y-8', className)}>
			{/* ================================================================
			 * Section 1: Colors (Theming)
			 * ================================================================ */}
			<section className="space-y-6">
				<SectionHeading
					title={__('Colors', 'productbay')}
					description={__('Customize the visual appearance of your table', 'productbay')}
				/>

				{/* Add to Cart Button - with hover colors */}
				<div className="space-y-4 hover:bg-orange-50 border border-transparent hover:border-orange-200 p-4 rounded-md m-0 mb-2">
					<h3 className="text-sm font-semibold text-gray-900 m-0 pb-4">
						{__('Add to Cart Button', 'productbay')}
					</h3>
					<ColorChoice
						labelBg={__('Background', 'productbay')}
						labelColor={__('Text', 'productbay')}
						bgColor={style.button.bgColor || '#2271b1'}
						textColor={style.button.textColor || '#ffffff'}
						onBgChange={(val) => setButtonStyle({ bgColor: val })}
						onColorChange={(val) => setButtonStyle({ textColor: val })}
					/>
					{/* Button Hover Colors */}
					<ColorChoice
						labelColor={__('Hover Text', 'productbay')}
						labelBg={__('Hover Background', 'productbay')}
						bgColor={style.button.hoverBgColor || '#135e96'}
						textColor={style.button.hoverTextColor || '#ffffff'}
						onBgChange={(val) => setButtonStyle({ hoverBgColor: val })}
						onColorChange={(val) => setButtonStyle({ hoverTextColor: val })}
						className="border-none pt-0"
					/>
				</div>

				{/* Table Header */}
				<div className="space-y-4 hover:bg-orange-50 border border-transparent hover:border-orange-200 p-4 rounded-md m-0 mb-2">
					<h3 className="text-sm font-semibold text-gray-900 m-0 pb-4">
						{__('Table Header', 'productbay')}
					</h3>
					<ColorChoice
						labelColor={__('Text', 'productbay')}
						labelBg={__('Background', 'productbay')}
						bgColor={style.header.bgColor || '#f0f0f1'}
						textColor={style.header.textColor || '#333333'}
						onBgChange={(val) => setHeaderStyle({ bgColor: val })}
						onColorChange={(val) => setHeaderStyle({ textColor: val })}
					/>
				</div>

				{/* Table Rows */}
				<div className="space-y-4 hover:bg-orange-50 border border-transparent hover:border-orange-200 p-4 rounded-md m-0 mb-2">
					<h3 className="text-sm font-semibold text-gray-900 m-0 pb-4">
						{__('Table Rows', 'productbay')}
					</h3>
					<ColorChoice
						labelBg={__('Background', 'productbay')}
						labelColor={__('Text', 'productbay')}
						bgColor={style.body.bgColor || '#ffffff'}
						textColor={style.body.textColor || '#444444'}
						onBgChange={(val) => setBodyStyle({ bgColor: val })}
						onColorChange={(val) => setBodyStyle({ textColor: val })}
					/>
				</div>

				{/* Alternate Rows (Zebra Striping) */}
				<div className="space-y-4 hover:bg-orange-50 border border-transparent hover:border-orange-200 p-4 rounded-md m-0 mb-2">
					<SettingsOption
						className="px-0 hover:bg-transparent hover:border-transparent"
						title={__('Alternate Rows (Zebra Striping)', 'productbay')}
						description={__(
							'Improve table readability with alternating row colors',
							'productbay'
						)}
					>
						<Toggle
							checked={style.body.rowAlternate}
							onChange={(e) =>
								setBodyStyle({
									rowAlternate: e.target.checked,
								})
							}
						/>
					</SettingsOption>

					<div
						className={cn(
							'transition-all duration-300 py-2 rounded-md',
							style.body.rowAlternate
								? 'opacity-100'
								: 'opacity-40 pointer-events-none grayscale'
						)}
					>
						<ColorChoice
							labelBg={__('Alternate Background', 'productbay')}
							labelColor={__('Alternate Text', 'productbay')}
							bgColor={style.body.altBgColor || '#f9f9f9'}
							textColor={style.body.altTextColor || '#444444'}
							onBgChange={(val) => setBodyStyle({ altBgColor: val })}
							onColorChange={(val) => setBodyStyle({ altTextColor: val })}
							className="border-none py-0"
						/>
					</div>
				</div>

				{/* Row Hover (Interaction) */}
				<div className="space-y-4 hover:bg-orange-50 border border-transparent hover:border-orange-200 p-4 rounded-md m-0 mb-2">
					<div className="flex items-center justify-between">
						<div>
							<h3 className="text-sm font-semibold text-gray-900 block m-0">
								{__('Row Hover Effect', 'productbay')}
							</h3>
							<p className="text-xs text-gray-500 m-0 mt-1">
								{__('Highlight rows when mouse hovers over them', 'productbay')}
							</p>
						</div>
						<Toggle
							checked={style.hover.rowHoverEnabled}
							onChange={(e) =>
								setHoverStyle({
									rowHoverEnabled: e.target.checked,
								})
							}
						/>
					</div>

					<div
						className={cn(
							'transition-all duration-300 px-4 py-2 rounded-md',
							style.hover.rowHoverEnabled
								? 'opacity-100'
								: 'opacity-40 pointer-events-none grayscale'
						)}
					>
						<ColorChoice
							labelBg={__('Hover Background', 'productbay')}
							labelColor={__('Hover Text', 'productbay')}
							bgColor={style.hover.rowHoverBgColor || '#f5f5f5'}
							textColor={style.hover.rowHoverTextColor || ''}
							onBgChange={(val) => setHoverStyle({ rowHoverBgColor: val })}
							onColorChange={(val) => setHoverStyle({ rowHoverTextColor: val })}
							className="border-none py-0"
						/>
					</div>
				</div>
			</section>

			{/* ================================================================
			 * Section 2: Layout & Spacing
			 * ================================================================ */}
			<section className="space-y-6">
				<SectionHeading
					title={__('Layout & Spacing', 'productbay')}
					description={__('Configure table borders and spacing', 'productbay')}
				/>

				{/* Border Style */}
				<SettingsOption
					title={__('Border Style', 'productbay')}
					description={__('Table border appearance', 'productbay')}
				>
					<div className="w-36">
						<Select
							size="sm"
							value={style.layout.borderStyle}
							onChange={(val) => setLayoutStyle({ borderStyle: val as any })}
							options={[
								{
									label: __('None', 'productbay'),
									value: 'none',
								},
								{
									label: __('Solid', 'productbay'),
									value: 'solid',
								},
								{
									label: __('Dashed', 'productbay'),
									value: 'dashed',
								},
							]}
						/>
					</div>
				</SettingsOption>

				{/* Border Color - dim when border style is none */}
				<div
					className={cn(
						'transition-all duration-300 m-0',
						style.layout.borderStyle !== 'none'
							? 'opacity-100'
							: 'opacity-40 pointer-events-none grayscale'
					)}
				>
					<SettingsOption
						title={__('Border Color', 'productbay')}
						description={__('Color of table borders', 'productbay')}
					>
						<ColorPicker
							value={style.layout.borderColor || '#e5e5e5'}
							onChange={(val) => setLayoutStyle({ borderColor: val })}
						/>
					</SettingsOption>
				</div>

				{/* Border Radius */}
				<SettingsOption
					className="px-4 py-2"
					title={__('Border Radius', 'productbay')}
					description={__('Corner roundness of the table', 'productbay')}
				>
					<div className="flex gap-2 items-center">
						<Toggle
							checked={style.layout.borderRadiusEnabled ?? true}
							onChange={(e) =>
								setLayoutStyle({
									borderRadiusEnabled: e.target.checked,
								})
							}
						/>
						<div
							className={cn(
								'flex items-center gap-2',
								style.layout.borderRadiusEnabled
									? 'opacity-100'
									: 'opacity-40 pointer-events-none grayscale'
							)}
						>
							<input
								type="number"
								min="0"
								max="24"
								value={parseInt(style.layout.borderRadius) || 0}
								onChange={(e) =>
									setLayoutStyle({
										borderRadius: `${e.target.value}px`,
									})
								}
								className="w-20 h-9 px-3 py-2 text-center border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
							/>
							<span className="text-sm text-gray-500">px</span>
						</div>
					</div>
				</SettingsOption>

				{/* Cell Padding */}
				<SettingsOption
					title={__('Cell Padding', 'productbay')}
					description={__('Amount of space inside table cells', 'productbay')}
				>
					<div className="w-36">
						<Select
							size="sm"
							value={style.layout.cellPadding}
							onChange={(val) => setLayoutStyle({ cellPadding: val as any })}
							options={[
								{
									label: __('Compact', 'productbay'),
									value: 'compact',
								},
								{
									label: __('Normal', 'productbay'),
									value: 'normal',
								},
								{
									label: __('Spacious', 'productbay'),
									value: 'spacious',
								},
							]}
						/>
					</div>
				</SettingsOption>
			</section>

			{/* ================================================================
			 * Section 3: Typography
			 * ================================================================ */}
			<section className="space-y-6">
				<SectionHeading
					title={__('Typography', 'productbay')}
					description={__('Font styling options', 'productbay')}
				/>

				{/* Header Font Weight */}
				<SettingsOption
					title={__('Header Font Weight', 'productbay')}
					description={__('Weight of text in table header row', 'productbay')}
				>
					<div className="w-36">
						<Select
							size="sm"
							value={style.typography.headerFontWeight}
							onChange={(val) =>
								setTypographyStyle({
									headerFontWeight: val as any,
								})
							}
							options={[
								{
									label: __('Normal', 'productbay'),
									value: 'normal',
								},
								{
									label: __('Bold', 'productbay'),
									value: 'bold',
								},
								{
									label: __('Extra Bold', 'productbay'),
									value: 'extrabold',
								},
							]}
						/>
					</div>
				</SettingsOption>

				{/* Header Text Transform */}
				<SettingsOption
					title={__('Header Text Transform', 'productbay')}
					description={__('Capitalization of text in table header row', 'productbay')}
				>
					<div className="w-36">
						<Select
							size="sm"
							value={style.typography.headerTextTransform || 'uppercase'}
							onChange={(val) =>
								setTypographyStyle({
									headerTextTransform: val as any,
								})
							}
							options={[
								{
									label: __('Uppercase', 'productbay'),
									value: 'uppercase',
								},
								{
									label: __('Lowercase', 'productbay'),
									value: 'lowercase',
								},
								{
									label: __('Capitalize', 'productbay'),
									value: 'capitalize',
								},
								{
									label: __('Normal', 'productbay'),
									value: 'normal-case',
								},
							]}
						/>
					</div>
				</SettingsOption>
			</section>

			{/* ================================================================
			 * Section 4: Mobile Layout (Responsive)
			 * ================================================================ */}
			<section className="space-y-6">
				<SectionHeading
					title={__('Mobile Layout', 'productbay')}
					description={__(
						'How the table adapts on phones and narrow screens',
						'productbay'
					)}
				/>

				{/* Responsive Mode */}
				<SettingsOption
					title={__('Layout on phones', 'productbay')}
					description={__(
						'Stacked cards turn each row into a labeled card, accordion collapses rows behind a tap-to-expand summary, and horizontal scroll keeps the full table.',
						'productbay'
					)}
				>
					<div className="w-44">
						<Select
							size="sm"
							value={
								style.responsive?.mode === 'accordion' ||
								style.responsive?.mode === 'scroll'
									? style.responsive.mode
									: 'stack'
							}
							onChange={(val) =>
								setResponsiveStyle({
									mode: val as TableStyle['responsive']['mode'],
								})
							}
							options={[
								{
									label: __('Stacked cards', 'productbay'),
									value: 'stack',
								},
								{
									label: __('Accordion', 'productbay'),
									value: 'accordion',
								},
								{
									label: __('Horizontal scroll', 'productbay'),
									value: 'scroll',
								},
							]}
						/>
					</div>
				</SettingsOption>
			</section>
		</div>
	);
};
