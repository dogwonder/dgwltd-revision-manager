/**
 * Revision Item Component
 *
 * Individual timeline item representing a single revision.
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const RevisionItem = ({ revision, isFirst, isLast, onSetCurrent, disabled }) => {
    const { id, title, author, date, status, is_current, excerpt } = revision;

    const getStatusIcon = (status) => {
        switch (status) {
            case 'current':
                return '●';
            case 'pending':
                return '◒';
            case 'past':
                return '○';
            default:
                return '○';
        }
    };

    const getStatusLabel = (status) => {
        switch (status) {
            case 'current':
                return __('Current', 'dgwltd-revision-manager');
            case 'pending':
                return __('Pending', 'dgwltd-revision-manager');
            case 'past':
                return __('Past', 'dgwltd-revision-manager');
            default:
                return __('Unknown', 'dgwltd-revision-manager');
        }
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <div className={`dgw-timeline-item dgw-timeline-item--${status} ${is_current ? 'dgw-timeline-item--current' : ''}`}>
            <div className="dgw-timeline-marker">
                <span className="dgw-timeline-icon" role="img" aria-label={getStatusLabel(status)}>
                    {getStatusIcon(status)}
                </span>
            </div>

            <div className="dgw-timeline-content">
                <div className="dgw-timeline-header">
                    <h4 className="dgw-timeline-title">
                        {title || __('(No title)', 'dgwltd-revision-manager')}
                    </h4>
                    <span className={`dgw-timeline-status dgw-timeline-status--${status}`}>
                        {getStatusLabel(status)}
                    </span>
                </div>

                <div className="dgw-timeline-meta">
                    <span className="dgw-timeline-author">
                        {__('By', 'dgwltd-revision-manager')} {author}
                    </span>
                    <span className="dgw-timeline-date">
                        {formatDate(date)}
                    </span>
                </div>

                {excerpt && (
                    <p className="dgw-timeline-excerpt">
                        {excerpt}
                    </p>
                )}

                <div className="dgw-timeline-actions">
                    {!is_current && (
                        <Button
                            isSmall
                            isSecondary
                            onClick={onSetCurrent}
                            disabled={disabled}
                        >
                            {__('Set as Current', 'dgwltd-revision-manager')}
                        </Button>
                    )}

                    <Button
                        isSmall
                        isTertiary
                        href={`/wp-admin/revision.php?revision=${id}`}
                        target="_blank"
                    >
                        {__('View', 'dgwltd-revision-manager')}
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default RevisionItem;