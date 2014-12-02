<?php

class NetDoktor_FixViewCountSorting_XenForo_ControllerPublic_Forum extends XenForo_ControllerPublic_Forum
{

    public function actionForum()
    {
        $forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
        $forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

        $ftpHelper = $this->getHelper('ForumThreadPost');
        $forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable(
            $forumId ? $forumId : $forumName,
            $this->_getForumFetchOptions()
        );
        $forumId = $forum['node_id'];

        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();
        $forumModel = $this->_getForumModel();

        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $threadsPerPage = XenForo_Application::get('options')->discussionsPerPage;

        $this->canonicalizeRequestUrl(
            XenForo_Link::buildPublicLink('forums', $forum, array('page' => $page))
        );

        list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultThreadSort($forum);

        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
        $orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

        $displayConditions = $this->_getDisplayConditions($forum);

        $fetchElements = $this->_getThreadFetchElements($forum, $displayConditions);
        $threadFetchConditions = $fetchElements['conditions'];
        $threadFetchOptions = $fetchElements['options'] + array(
                'perPage' => $threadsPerPage,
                'page' => $page,
                'order' => $order,
                'orderDirection' => $orderDirection
            );
        unset($fetchElements);

        $totalThreads = $threadModel->countThreadsInForum($forumId, $threadFetchConditions);

        $this->canonicalizePageNumber($page, $threadsPerPage, $totalThreads, 'forums', $forum);

        $threads = $threadModel->getThreadsInForum($forumId, $threadFetchConditions, $threadFetchOptions);

        if ($page == 1)
        {
            $stickyThreadFetchOptions = $threadFetchOptions;
            unset($stickyThreadFetchOptions['perPage'], $stickyThreadFetchOptions['page']);

            $stickyThreads = $threadModel->getStickyThreadsInForum($forumId, $threadFetchConditions, $stickyThreadFetchOptions);
        }
        else
        {
            $stickyThreads = array();
        }

        // prepare all threads for the thread list
        $inlineModOptions = array();
        $permissions = $visitor->getNodePermissions($forumId);

        foreach ($threads AS &$thread)
        {
            $threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
            $inlineModOptions += $threadModOptions;

            $thread = $threadModel->prepareThread($thread, $forum, $permissions);
        }
        foreach ($stickyThreads AS &$thread)
        {
            $threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
            $inlineModOptions += $threadModOptions;

            $thread = $threadModel->prepareThread($thread, $forum, $permissions);
        }

        // diese Stelle wurde geändert!
        if ($order == 'view_count') {
            // the view_count is sometimes modified by the 'prepareThread' method, so we have to order all threads again
            // depending on the order-type and asc/desc direction
            function cmp_desc($a, $b) {
                return intval($b['view_count']) - intval($a['view_count']);
            }
            function cmp_asc($a, $b) {
                return intval($a['view_count']) - intval($b['view_count']);
            }
            usort($threads, 'cmp_'.$orderDirection);
        }

        unset($thread);

        // if we've read everything on the first page of a normal sort order, probably need to mark as read
        if ($visitor['user_id'] && $page == 1 && !$displayConditions
            && $order == 'last_post_date' && $orderDirection == 'desc'
            && $forum['forum_read_date'] < $forum['last_post_date']
        )
        {
            $hasNew = false;
            foreach ($threads AS $thread)
            {
                if ($thread['isNew'] && !$thread['isIgnored'])
                {
                    $hasNew = true;
                    break;
                }
            }

            if (!$hasNew)
            {
                // everything read, but forum not marked as read. Let's check.
                $this->_getForumModel()->markForumReadIfNeeded($forum);
            }
        }

        // get the ordering params set for the header links
        $orderParams = array();
        foreach ($this->_getThreadSortFields($forum) AS $field)
        {
            $orderParams[$field] = $displayConditions;
            $orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
            if ($order == $field)
            {
                $orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
            }
        }

        $pageNavParams = $displayConditions;
        $pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
        $pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

        $viewParams = array(
            'nodeList' => $this->_getNodeModel()->getNodeDataForListDisplay($forum, 0),
            'forum' => $forum,
            'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum, true),

            'canPostThread' => $forumModel->canPostThreadInForum($forum),
            'canSearch' => $visitor->canSearch(),
            'canWatchForum' => $forumModel->canWatchForum($forum),

            'inlineModOptions' => $inlineModOptions,
            'threads' => $threads,
            'stickyThreads' => $stickyThreads,

            'ignoredNames' => $this->_getIgnoredContentUserNames($threads) + $this->_getIgnoredContentUserNames($stickyThreads),

            'order' => $order,
            'orderDirection' => $orderDirection,
            'orderParams' => $orderParams,
            'displayConditions' => $displayConditions,

            'pageNavParams' => $pageNavParams,
            'page' => $page,
            'threadStartOffset' => ($page - 1) * $threadsPerPage + 1,
            'threadEndOffset' => ($page - 1) * $threadsPerPage + count($threads),
            'threadsPerPage' => $threadsPerPage,
            'totalThreads' => $totalThreads,

            'showPostedNotice' => $this->_input->filterSingle('posted', XenForo_Input::UINT)
        );

        return $this->responseView('XenForo_ViewPublic_Forum_View', 'forum_view', $viewParams);
    }

}