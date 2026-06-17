import {
	RadioIcon,
	LoaderIcon,
	AlertCircleIcon,
	Maximize2Icon,
	XIcon,
	MonitorIcon,
	TabletIcon,
	SmartphoneIcon,
	RefreshCwIcon,
	InfoIcon,
} from 'lucide-react';
import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import ProductBayIcon from '@/components/ui/ProductBayIcon';
import { useTableStore } from '@/store/tableStore';
import { useDebounce } from '@/hooks/useDebounce';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/context/ToastContext';
import { Modal } from '@/components/ui/Modal';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import { apiFetch } from '@/utils/api';
import { __ } from '@wordpress/i18n';
import { cn } from '@/utils/cn';

export interface LivePreviewProps {
	className?: string;
}

interface PreviewResponse {
	html: string;
	cssUrls: string[];
}

/**
 * Inline JS injected into the preview iframe.
 *
 * Handles:
 * - Bulk select: checkbox changes update the "Add to Cart" button text with
 *   the total count and price of selected products.
 * - Select all: toggles all product checkboxes.
 * - Add to Cart: sends a postMessage to the parent React app (which shows a toast).
 * - Auto-resize: posts the document height so the parent can resize the iframe.
 * - Link intercept: prevents navigation when clicking product links in the preview.
 */
const getIframeScript = (addToCartText: string) => `
<script>
(function() {
    var selectedProducts = {};
    var addToCartText = ${JSON.stringify(addToCartText)};

    /**
     * Recalculate selected count/total and update the bulk button text.
     */
    function updateBulkButton() {
        var btn = document.querySelector('.productbay-btn-bulk');
        if (!btn) return;

        var keys = Object.keys(selectedProducts);
        var count = keys.length;
        var total = 0;
        keys.forEach(function(k) { total += selectedProducts[k]; });

        if (count > 0) {
            btn.textContent = 'Add ' + count + ' item' + (count > 1 ? 's' : '') + ' for $' + total.toFixed(2);
            btn.disabled = false;
        } else {
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="display:inline-block;vertical-align:middle"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> ' + addToCartText;
            btn.disabled = true;
        }
    }

    /**
     * Post the current document height to the parent so it can resize the iframe.
     */
    function postHeight() {
        var wrapper = document.querySelector('.productbay-wrapper');
        // Calculate true height of the content wrapper, plus the 32px of body padding configured in the style tag below.
        // If wrapper doesn't exist, fallback to body scrollHeight.
        var h = wrapper ? (wrapper.getBoundingClientRect().height + 32) : document.body.scrollHeight;
        
        // Ceil the height to avoid subpixel fractions causing endless resize triggers
        window.parent.postMessage({ type: 'resize', height: Math.ceil(h) }, '*');
    }

    // --- Event Delegation ---
    document.addEventListener('change', function(e) {
        var target = e.target;

        // Select All checkbox
        if (target.classList.contains('productbay-select-all')) {
            var boxes = document.querySelectorAll('.productbay-select-product');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = target.checked;
                var id = boxes[i].value;
                var price = parseFloat(boxes[i].getAttribute('data-price') || '0');
                if (target.checked) {
                    selectedProducts[id] = price;
                } else {
                    delete selectedProducts[id];
                }
            }
            updateBulkButton();
            return;
        }

        // Individual product checkbox
        if (target.classList.contains('productbay-select-product')) {
            var id = target.value;
            var price = parseFloat(target.getAttribute('data-price') || '0');
            if (target.checked) {
                selectedProducts[id] = price;
            } else {
                delete selectedProducts[id];
                // Uncheck "Select All" when any individual is unchecked
                var selectAll = document.querySelector('.productbay-select-all');
                if (selectAll) selectAll.checked = false;
            }
            updateBulkButton();
        }
    });

    // Add to Cart click — notify parent via postMessage
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.productbay-btn-bulk');
        if (btn) {
            e.preventDefault();
            if (Object.keys(selectedProducts).length === 0) return;
            var ids = Object.keys(selectedProducts);
            window.parent.postMessage({ type: 'addToCart', productIds: ids }, '*');
        }

        // Intercept all anchor clicks to prevent navigation inside the preview
        var anchor = e.target.closest('a');
        if (anchor) {
            e.preventDefault();
        }
    });

    // Auto-resize on load and on any DOM mutation
    window.addEventListener('load', postHeight);
    
    // To prevent infinite resize loops, we use a debounce and only observe 
    // the content wrapper instead of the entire body if possible.
    var resizeTimeout;
    var observer = new MutationObserver(function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(postHeight, 50);
    });
    
    // Wait a tick for the React output to render
    setTimeout(function() {
        var wrapper = document.querySelector('.productbay-wrapper') || document.body;
        observer.observe(wrapper, { 
            childList: true, 
            subtree: true,
            attributes: true, 
            // Ignore style changes to prevent loop when parent resizes iframe height
            attributeFilter: ['class', 'id', 'data-status'] 
        });
        postHeight();
    }, 100);
})();
</script>
`;

/**
 * Build a complete HTML document string for the preview iframe.
 *
 * Combines:
 * - A CSS reset to avoid browser default inconsistencies
 * - The frontend stylesheet via <link> for base table styles
 * - The TableRenderer HTML output (which includes its own <style> block)
 * - The interactivity script for bulk select & postMessage communication
 *
 * @param html  - The rendered HTML from TableRenderer.php
 * @param cssUrls - Array of URLs to frontend stylesheets
 * @param addToCartText - The localized/customized Add to Cart text
 */
const buildSrcdoc = (html: string, cssUrls: string[], addToCartText: string): string => `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ${cssUrls.map((url) => `<link rel="stylesheet" href="${url}" />`).join('\n    ')}
    <style>
        /* Iframe body setup — the scoped reset is in frontend.css */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 14px;
            color: #334155;
            background: transparent;
            padding: 16px;
            margin: 0;
            overflow-x: hidden;
        }
        .productbay-wrapper { width: 100%; }
        table { width: 100%; }
    </style>
</head>
<body>
    ${html}
    ${getIframeScript(addToCartText)}
</body>
</html>
`;

const DEVICE_WIDTHS = {
	desktop: 1200,
	tablet: 768,
	mobile: 375,
};

/**
 * LivePreview Component
 *
 * Displays a live preview panel for table configuration.
 * Fetches server-side rendered HTML based on current store state and renders
 * it inside an iframe for complete CSS isolation from the admin panel.
 *
 * Features:
 * - Iframe-based rendering: only TableRenderer styles and frontend.css apply
 * - Interactive bulk select: updates product count & total price
 * - Toast notification on "Add to Cart" click (no redirect)
 * - Auto-resizing iframe height
 * - Full-screen modal support
 * - Device switcher (Desktop, Tablet, Mobile)
 * - Responsive scaling for smaller containers
 */
const LivePreview = ({ className }: LivePreviewProps) => {
	const { tableId, tableTitle, tableStatus, source, columns, settings, style } = useTableStore();

	const globalSettings = (window as any).productBaySettings || {};
	const globalAddToCartText = globalSettings.add_to_cart_text || __('Add to Cart', 'productbay');
	const tableAddToCartText = settings.cart?.addToCartText;
	const activeAddToCartText = tableAddToCartText || globalAddToCartText;

	const { toast } = useToast();

	// Memoize the payload to avoid effect triggering on every render
	const payload = useMemo(
		() => ({
			id: tableId,
			title: tableTitle,
			status: tableStatus,
			source,
			columns,
			settings,
			style,
		}),
		[tableId, tableTitle, tableStatus, source, columns, settings, style]
	);

	// Debounce the payload to avoid flooding the API
	const debouncedPayload = useDebounce(payload, 500);

	const [html, setHtml] = useState<string>('');
	const [cssUrls, setCssUrls] = useState<string[]>([]);
	const [loading, setLoading] = useState<boolean>(false);
	const [error, setError] = useState<string | null>(null);
	const [isFullscreen, setIsFullscreen] = useState(false);

	// Iframe height tracking (auto-resize based on content)
	const [iframeHeight, setIframeHeight] = useState(400);

	// Scaling State — start at 0 so the iframe is hidden until properly measured
	const [scale, setScale] = useState(0);
	const [activeDevice, setActiveDevice] = useState<keyof typeof DEVICE_WIDTHS>('desktop');
	const containerRef = useRef<HTMLDivElement>(null);

	// Retry counter — bumping this forces a refetch independent of payload
	const [retryCount, setRetryCount] = useState(0);

	// Extracted fetch function so it can be called from the retry button
	const fetchPreview = useCallback(async () => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch<PreviewResponse>('preview', {
				method: 'POST',
				body: JSON.stringify({ data: debouncedPayload }),
			});

			if (response && typeof response.html === 'string') {
				setHtml(response.html);
				if (Array.isArray(response.cssUrls)) {
					setCssUrls(response.cssUrls);
				}
			} else {
				throw new Error('Invalid response format');
			}
		} catch (err) {
			setError(err instanceof Error ? err.message : 'Failed to load preview');
			console.error('Preview Error:', err);
		} finally {
			setLoading(false);
		}
	}, [debouncedPayload]);

	// Effect to fetch preview when debounced payload or retry count changes
	useEffect(() => {
		fetchPreview();
	}, [fetchPreview, retryCount]);

	/**
	 * Listen for postMessage events from the preview iframe.
	 * Handles:
	 * - 'resize': auto-resize iframe height to match content
	 * - 'addToCart': show a toast notification (no redirect)
	 */
	const handleMessage = useCallback(
		(event: MessageEvent) => {
			const data = event.data;
			if (!data || typeof data !== 'object') return;

			if (data.type === 'resize' && typeof data.height === 'number') {
				setIframeHeight(data.height);
			}

			if (data.type === 'addToCart' && Array.isArray(data.productIds)) {
				const count = data.productIds.length;
				toast({
					title: __('Preview Only', 'productbay'),
					description: `${count} product${count > 1 ? 's' : ''
						} would be added to cart. Save and use the shortcode to enable real Add to Cart.`,
					type: 'info',
					duration: 4000,
				});
			}
		},
		[toast]
	);

	useEffect(() => {
		window.addEventListener('message', handleMessage);
		return () => window.removeEventListener('message', handleMessage);
	}, [handleMessage]);

	// Build the srcdoc string for the iframe (memoized to avoid re-renders)
	const srcdoc = useMemo(() => {
		if (!html || cssUrls.length === 0) return '';
		return buildSrcdoc(html, cssUrls, activeAddToCartText);
	}, [html, cssUrls, activeAddToCartText]);

	// Handle Scaling for the inline (non-fullscreen) preview
	useEffect(() => {
		const updateScale = () => {
			if (containerRef.current) {
				const { width } = containerRef.current.getBoundingClientRect();
				const targetWidth = DEVICE_WIDTHS[activeDevice];
				const newScale = width < targetWidth ? width / targetWidth : 1;

				// Prevent micro-vibrations from subpixel rendering differences
				// or scrollbar toggling loops.
				setScale((prev) => {
					return Math.abs(prev - newScale) > 0.005 ? newScale : prev;
				});
			}
		};

		// Use rAF to ensure the container is laid out before measuring
		requestAnimationFrame(updateScale);

		const observer = new ResizeObserver(updateScale);
		if (containerRef.current) {
			observer.observe(containerRef.current);
		}

		return () => observer.disconnect();
	}, [activeDevice, srcdoc]);

	/**
	 * Render the isolated iframe preview.
	 * Uses srcdoc to inject a complete HTML document with only the frontend
	 * stylesheet and TableRenderer output — no admin/Tailwind CSS leaks.
	 *
	 * @param fullWidth - If true, renders at full width (for fullscreen modal)
	 */
	const renderIframe = (fullWidth = false) => {
		const targetWidth = DEVICE_WIDTHS[activeDevice];

		return (
			<iframe
				srcDoc={srcdoc}
				title={__('Table Preview', 'productbay')}
				style={{
					width: fullWidth
						? activeDevice === 'desktop'
							? '100%'
							: `${targetWidth}px`
						: `${targetWidth}px`,
					height: `${iframeHeight}px`,
					border: 'none',
					display: 'block',
					margin: fullWidth ? '0 auto' : '0',
					...(fullWidth
						? {}
						: {
							transform: `scale(${scale})`,
							transformOrigin: 'top left',
						}),
				}}
				sandbox="allow-scripts"
			/>
		);
	};

	/**
	 * Device Switcher Component
	 */
	const DeviceSwitcher = ({ showLabels = false }: { showLabels?: boolean }) => (
		<div className="flex items-center gap-1 bg-gray-100 p-1 rounded-md border border-gray-200">
			<button
				onClick={() => setActiveDevice('desktop')}
				className={cn(
					'flex items-center gap-2 p-1 px-2 border border-transparent rounded cursor-pointer',
					activeDevice === 'desktop'
						? 'bg-blue-600 text-white shadow-sm'
						: 'text-gray-500 hover:bg-white hover:border-blue-600'
				)}
				title={__('Desktop View', 'productbay')}
			>
				<MonitorIcon className="w-3.5 h-3.5" />
				{showLabels && (
					<span className="text-[10px] font-bold uppercase tracking-wider">
						{__('Desktop', 'productbay')}
					</span>
				)}
			</button>
			<button
				onClick={() => setActiveDevice('tablet')}
				className={cn(
					'flex items-center gap-2 p-1 px-2 border border-transparent rounded cursor-pointer',
					activeDevice === 'tablet'
						? 'bg-blue-600 text-white shadow-sm'
						: 'text-gray-500 hover:bg-white hover:border-blue-600'
				)}
				title={__('Tablet View', 'productbay')}
			>
				<TabletIcon className="w-3.5 h-3.5" />
				{showLabels && (
					<span className="text-[10px] font-bold uppercase tracking-wider">
						{__('Tablet', 'productbay')}
					</span>
				)}
			</button>
			<button
				onClick={() => setActiveDevice('mobile')}
				className={cn(
					'flex items-center gap-2 p-1 px-2 border border-transparent rounded cursor-pointer',
					activeDevice === 'mobile'
						? 'bg-blue-600 text-white shadow-sm'
						: 'text-gray-500 hover:bg-white hover:border-blue-600'
				)}
				title={__('Mobile View', 'productbay')}
			>
				<SmartphoneIcon className="w-3.5 h-3.5" />
				{showLabels && (
					<span className="text-[10px] font-bold uppercase tracking-wider">
						{__('Mobile', 'productbay')}
					</span>
				)}
			</button>
		</div>
	);

	/**
	 * Limitation Alert
	 */
	const LimitationAlert = (
		<Alert variant="default" className="w-full bg-blue-50 text-gray-800">
			<InfoIcon className="w-4 h-4 shrink-0 mt-0.5 text-blue-600" />
			<AlertDescription className={isFullscreen ? 'text-sm' : 'text-xs'}>
				{__(
					'Actions such as search, pagination, and Add to Cart do not function in the Live Preview. Please save the table and view it on a live page to test these features.',
					'productbay'
				)}
			</AlertDescription>
		</Alert>
	);

	return (
		<div className={cn('w-full relative flex flex-col', className)}>
			{/* Header */}
			<div className="flex gap-2 items-center justify-between mb-0">
				<div className="relative z-10 inline-flex items-center px-4 py-3 text-sm font-semibold bg-white rounded-t-lg border border-gray-200 border-b-white w-fit">
					<RadioIcon
						className={cn('w-5 h-5 mr-2', loading ? 'animate-pulse text-blue-500' : '')}
					/>
					{__('Live Preview', 'productbay')}
				</div>
				{/* Header Actions */}
				<div className="mb-px flex items-center gap-3">
					<DeviceSwitcher />
					<Button
						title={__('Full Screen', 'productbay')}
						variant="outline"
						size="xs"
						onClick={() => setIsFullscreen(true)}
						className="hover:bg-white hover:border-blue-600 cursor-pointer p-2"
					>
						<Maximize2Icon className="w-5 h-5 text-gray-500" />
					</Button>
				</div>
			</div>

			{/* Content Area */}
			<div className="relative -mt-px bg-white rounded-b-lg rounded-tr-lg border border-gray-200 overflow-hidden">
				<div className="w-full p-4 bg-gray-50/50 relative overflow-hidden">
					{error ? (
						<div className="flex flex-col items-center justify-center h-full gap-3">
							<AlertCircleIcon className="w-10 h-10 text-red-400" />
							<p className="text-sm text-gray-600 font-medium">{error}</p>
							<button
								onClick={() => setRetryCount((c) => c + 1)}
								disabled={loading}
								className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors cursor-pointer disabled:opacity-50"
							>
								<RefreshCwIcon
									className={cn('w-4 h-4', loading && 'animate-spin')}
								/>
								{loading
									? __('Regenerating…', 'productbay')
									: __('Re-render Preview', 'productbay')}
							</button>
							<p className="text-xs text-gray-400 mt-1">
								{__('If the issue persists, try reloading the page.', 'productbay')}
							</p>
						</div>
					) : srcdoc ? (
						<div className="flex flex-col gap-4 w-full">
							{/* Wrapper for scaling — iframe renders at DESKTOP_WIDTH then scales down */}
							<div
								ref={containerRef}
								className="w-full origin-top-left relative rounded-md overflow-hidden ring-1 ring-gray-200/50"
								style={{
									height: `${iframeHeight * scale}px`,
									transition: 'height 0.2s ease-in-out',
								}}
							>
								{renderIframe()}
								{/* Pulse overlay while regenerating */}
								{loading && (
									<div className="absolute inset-0 bg-white/60 backdrop-blur-[1px] flex items-center justify-center rounded-lg animate-pulse transition-opacity">
										<LoaderIcon className="w-6 h-6 text-blue-500 animate-spin" />
									</div>
								)}
							</div>

							{/* Show Limitation Alert */}
							{LimitationAlert}
						</div>
					) : (
						<div className="flex items-center justify-center h-full text-gray-400">
							{loading ? (
								<LoaderIcon className="w-8 h-8 animate-spin" />
							) : (
								<p>{__('Loading preview...', 'productbay')}</p>
							)}
						</div>
					)}
				</div>
			</div>

			{/* Full Screen Modal */}
			<Modal
				isOpen={isFullscreen}
				onClose={() => setIsFullscreen(false)}
				fullScreen
				hideFooter
				header={
					<div className="flex items-center justify-between bg-white px-6 py-4 mt-8 shadow-sm border-b border-gray-200">
						{/* Title and Icon */}
						<div className="flex items-center gap-3 flex-1">
							<RadioIcon className="w-6 h-6 text-blue-600" />
							<h2 className="text-lg font-bold text-gray-800 m-0">
								{__('Table Preview', 'productbay')}
							</h2>
						</div>

						{/* Centered Device Switcher */}
						<div className="flex-1 flex justify-center">
							<DeviceSwitcher showLabels />
						</div>

						{/* Close Button */}
						<div className="flex-1 flex justify-end">
							<Button
								variant="ghost"
								onClick={() => setIsFullscreen(false)}
								className="bg-transparent hover:bg-red-50 border border-transparent hover:border-red-600 cursor-pointer p-2 rounded-full"
							>
								<XIcon className="w-6 h-6" />
								<span className="sr-only">{__('Close', 'productbay')}</span>
							</Button>
						</div>
					</div>
				}
			>
				<div className="bg-gray-100/95 backdrop-blur-sm min-h-full p-2 md:p-4 xl:p-6 flex flex-col">
					{/* Centered Container for the Table */}
					<div className="max-w-[1280px] w-full mx-auto flex flex-col gap-4">
						{/* Limitation Alert */}
						{srcdoc && LimitationAlert}
						{/* Table Preview */}
						<div className="bg-white min-h-[200px] rounded-lg p-2 xl:p-4 border border-gray-200 shadow-sm">
							{srcdoc ? (
								renderIframe(true)
							) : (
								<div className="flex items-center justify-center h-64 text-gray-400">
									<ProductBayIcon className="animate-pulse size-12" />
								</div>
							)}
						</div>
					</div>
				</div>
			</Modal>
		</div>
	);
};

export default LivePreview;
