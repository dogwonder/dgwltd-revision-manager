/**
 * Revision Editor Enhancements
 *
 * Enhances the WordPress revision.php page with timeline context and "Set as Current" functionality.
 */

import './revision-editor.scss';

import { render, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner, Notice, Modal } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const RevisionEditorEnhancements = () => {
    const [loading, setLoading] = useState(false);
    const [revisionData, setRevisionData] = useState(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [notice, setNotice] = useState(null);

    const { revisionId, postId, canManageRevisions } = dgwRevisionEditor;

    useEffect(() => {
        if (postId) {
            loadRevisionData();
        }
    }, [postId]);

    const loadRevisionData = async () => {
        setLoading(true);

        try {
            const response = await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${postId}`,
            });

            setRevisionData(response);
        } catch (err) {
            console.error('Error loading revision data:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleSetCurrent = async () => {
        setLoading(true);
        setNotice(null);

        try {
            const response = await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${revisionId}/set-current`,
                method: 'POST'
            });

            if (response.success) {
                setNotice({
                    type: 'success',
                    message: dgwRevisionEditor.strings.success
                });

                // Reload data
                await loadRevisionData();
            } else {
                setNotice({
                    type: 'error',
                    message: dgwRevisionEditor.strings.error
                });
            }
        } catch (err) {
            setNotice({
                type: 'error',
                message: dgwRevisionEditor.strings.error
            });
            console.error('Error setting current revision:', err);
        } finally {
            setLoading(false);
            setShowConfirm(false);
        }
    };

    const getCurrentRevision = () => {
        if (!revisionData?.timeline) return null;
        return revisionData.timeline.find(rev => rev.id === parseInt(revisionId));
    };

    const getTimelineContext = () => {
        if (!revisionData?.timeline) return null;

        const currentIndex = revisionData.timeline.findIndex(rev => rev.id === parseInt(revisionId));
        if (currentIndex === -1) return null;

        const before = revisionData.timeline.slice(0, currentIndex);
        const after = revisionData.timeline.slice(currentIndex + 1);

        return { before, after, current: revisionData.timeline[currentIndex] };
    };

    const currentRevision = getCurrentRevision();
    const timelineContext = getTimelineContext();
    const isCurrentRevision = currentRevision?.is_current;

    return (
        <div className="dgw-revision-editor-enhancements">
            {notice && (
                <Notice
                    status={notice.type}
                    isDismissible
                    onRemove={() => setNotice(null)}
                >
                    {notice.message}
                </Notice>
            )}

            <div className="dgw-revision-actions">
                <h3>{dgwRevisionEditor.strings.revisionTimeline}</h3>

                {loading ? (
                    <div className="dgw-loading">
                        <Spinner />
                        <span>{__('Loading...', 'dgwltd-revision-manager')}</span>
                    </div>
                ) : (
                    <>
                        {canManageRevisions && !isCurrentRevision && (
                            <Button
                                isPrimary
                                onClick={() => setShowConfirm(true)}
                                disabled={loading}
                            >
                                {dgwRevisionEditor.strings.setAsCurrent}
                            </Button>
                        )}

                        {isCurrentRevision && (
                            <div className="dgw-current-indicator">
                                <strong>{__('This is the current revision', 'dgwltd-revision-manager')}</strong>
                            </div>
                        )}

                        {timelineContext && (
                            <TimelineContext context={timelineContext} />
                        )}
                    </>
                )}
            </div>

            {showConfirm && (
                <Modal
                    title={dgwRevisionEditor.strings.setAsCurrent}
                    onRequestClose={() => setShowConfirm(false)}
                >
                    <p>{dgwRevisionEditor.strings.confirmSetCurrent}</p>

                    <div className="dgw-modal-actions">
                        <Button
                            isSecondary
                            onClick={() => setShowConfirm(false)}
                            disabled={loading}
                        >
                            {__('Cancel', 'dgwltd-revision-manager')}
                        </Button>
                        <Button
                            isPrimary
                            onClick={handleSetCurrent}
                            disabled={loading}
                        >
                            {loading ? <Spinner /> : dgwRevisionEditor.strings.setAsCurrent}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
};

const TimelineContext = ({ context }) => {
    const { before, after, current } = context;

    return (
        <div className="dgw-timeline-context">
            <h4>{__('Timeline Context', 'dgwltd-revision-manager')}</h4>

            <div className="dgw-context-timeline">
                {before.length > 0 && (
                    <div className="dgw-context-section">
                        <h5>{__('Newer Revisions', 'dgwltd-revision-manager')} ({before.length})</h5>
                        <ul>
                            {before.slice(0, 3).map(rev => (
                                <li key={rev.id}>
                                    <a href={`/wp-admin/revision.php?revision=${rev.id}`}>
                                        {rev.title || __('(No title)', 'dgwltd-revision-manager')}
                                    </a>
                                    <span className={`status-${rev.status}`}>
                                        {rev.status}
                                    </span>
                                </li>
                            ))}
                            {before.length > 3 && (
                                <li>
                                    <em>{__('and %d more...', 'dgwltd-revision-manager').replace('%d', before.length - 3)}</em>
                                </li>
                            )}
                        </ul>
                    </div>
                )}

                <div className="dgw-context-current">
                    <h5>{__('Current Viewing', 'dgwltd-revision-manager')}</h5>
                    <div className="dgw-current-revision">
                        <strong>{current.title || __('(No title)', 'dgwltd-revision-manager')}</strong>
                        <span className={`status-${current.status} ${current.is_current ? 'is-current' : ''}`}>
                            {current.status}
                            {current.is_current && ' (Active)'}
                        </span>
                    </div>
                </div>

                {after.length > 0 && (
                    <div className="dgw-context-section">
                        <h5>{__('Older Revisions', 'dgwltd-revision-manager')} ({after.length})</h5>
                        <ul>
                            {after.slice(0, 3).map(rev => (
                                <li key={rev.id}>
                                    <a href={`/wp-admin/revision.php?revision=${rev.id}`}>
                                        {rev.title || __('(No title)', 'dgwltd-revision-manager')}
                                    </a>
                                    <span className={`status-${rev.status}`}>
                                        {rev.status}
                                    </span>
                                </li>
                            ))}
                            {after.length > 3 && (
                                <li>
                                    <em>{__('and %d more...', 'dgwltd-revision-manager').replace('%d', after.length - 3)}</em>
                                </li>
                            )}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.createElement('div');
    container.id = 'dgw-revision-editor-enhancements';

    // Insert after the revision title or at the top of content
    const titleElement = document.querySelector('.revisions-meta') || document.querySelector('#revision-diff');
    if (titleElement) {
        titleElement.parentNode.insertBefore(container, titleElement.nextSibling);
    } else {
        document.body.appendChild(container);
    }

    render(<RevisionEditorEnhancements />, container);
});