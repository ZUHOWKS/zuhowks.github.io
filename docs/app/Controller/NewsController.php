<?php

class NewsController extends AppController
{

    public $components = ['Session'];

    function blog()
    {
        // récupérage des news
        $search_news = $this->getNews(); // on charge le model

        if ($this->isConnected) {
            $i = 0;
            foreach ($search_news as $news => $val) {
                foreach ($news['Like'] as $k => $value) {
                    foreach ($value as $column => $v) {
                        if ($this->User->getKey('id') == $v) {
                            $search_news[$i]['News']['liked'] = true;
                        }
                    }
                }
                $i++;
            }
        }
        $i = 0;
        foreach ($search_news as $news => $val) {
            if (!isset($news['News']['liked'])) {
                $search_news[$i]['News']['liked'] = false;
            }
            $i++;
        }

        $can_like = $this->Permissions->can('LIKE_NEWS');

        $this->set(compact('search_news', 'can_like'));
    }

    function api()
    {

        $this->autoRender = false;
        $this->response->type('json');

        // récupérage des news
        $search_news = $this->getNews();

        $this->response->body(json_encode($search_news));
    }

    function index($slug)
    {
        $this->layout = $this->Configuration->getKey('layout');

        if (isset($slug)) { // si le slug est présent
            $this->loadModel('News'); // on charge le model
            $news = $this->News->find('first', ['recursive' => 1, 'order' => 'id desc', 'conditions' => ['slug' => $slug]]); // on cherche les 3 dernières news (les plus veille)
            if ($news) { // si le slug existe

                if ($this->isConnected) {
                    foreach ($news['Like'] as $k => $value) {
                        foreach ($value as $column => $v) {
                            if ($this->User->getKey('id') == $v) {
                                $news['News']['liked'] = true;
                            }
                        }
                    }
                }
                if (!isset($news['News']['liked'])) {
                    $news['News']['liked'] = false;
                }

                $this->set('title_for_layout', $news['News']['title']);

                // on chercher les 4 dernières news pour la sidebar
                $search_news = $this->News->find('all', ['limit' => '4', 'order' => 'id desc', 'conditions' => ['published' => 1]]); // on cherche les 3 dernières news (les plus veille)
                $this->set(compact('search_news', 'news')); // on envoie les données à la vue
            } else {
                throw new NotFoundException();
            }
        } else {
            throw new NotFoundException();
        }
    }

    function add_comment()
    {
        $this->autoRender = false;
        $this->response->type('json');
        if ($this->request->is('post')) {
            if ($this->Permissions->can('COMMENT_NEWS')) {
                if (!empty($this->request->data['content']) && !empty($this->request->data['news_id'])) {

                    $event = new CakeEvent('beforeAddComment', $this, ['content' => $this->request->data['content'], 'news_id' => $this->request->data['news_id'], 'user' => $this->User->getAllFromCurrentUser()]);
                    $this->getEventManager()->dispatch($event);
                    if ($event->isStopped()) {
                        return $event->result;
                    }

                    $this->loadModel('Comment');
                    $this->Comment->create();
                    $this->Comment->set([
                        'content' => $this->request->data['content'],
                        'user_id' => $this->User->getKey('id'),
                        'news_id' => intval($this->request->data['news_id'])
                    ]);
                    $this->Comment->save();

                    $this->response->body(json_encode(['statut' => true, 'msg' => 'success']));
                } else {
                    $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')]));
                }
            } else {
                $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('USER__ERROR_MUST_BE_LOGGED')]));
            }
        } else {
            $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST')]));
        }
    }

    function like()
    {
        $this->autoRender = false;
        if ($this->request->is('post')) {
            if ($this->Permissions->can('LIKE_NEWS')) {
                $this->loadModel('Like');
                $already = $this->Like->find('all', ['conditions' => ['news_id' => $this->request->data['id'], 'user_id' => $this->User->getKey('id')]]);
                if (empty($already)) {

                    $event = new CakeEvent('beforeLike', $this, ['news_id' => $this->request->data['id'], 'user' => $this->User->getAllFromCurrentUser()]);
                    $this->getEventManager()->dispatch($event);
                    if ($event->isStopped()) {
                        return $event->result;
                    }

                    $this->Like->read(null, null);
                    $this->Like->set(['news_id' => $this->request->data['id'], 'user_id' => $this->User->getKey('id')]);
                    $this->Like->save();
                }
            }
        }
    }

    function dislike()
    {
        $this->autoRender = false;
        if ($this->request->is('post')) {
            if ($this->Permissions->can('LIKE_NEWS')) {
                $this->loadModel('Like');
                $already = $this->Like->find('all', ['conditions' => ['news_id' => $this->request->data['id'], 'user_id' => $this->User->getKey('id')]]);
                if (!empty($already)) {

                    $event = new CakeEvent('beforeDislike', $this, ['news_id' => $this->request->data['id'], 'user' => $this->User->getAllFromCurrentUser()]);
                    $this->getEventManager()->dispatch($event);
                    if ($event->isStopped()) {
                        return $event->result;
                    }

                    $this->Like->deleteAll(['Like.news_id' => $this->request->data['id'], 'Like.user_id' => $this->User->getKey('id')]);
                }
            }
        }
    }


    function ajax_comment_delete()
    {
        $this->autoRender = false;
        $this->loadModel('Comment');
        $search = $this->Comment->find('all', ['conditions' => ['id' => $this->request->data['id']]]);
        if ($this->Permissions->can('DELETE_COMMENT') or $this->Permissions->can('DELETE_HIS_COMMENT') and $this->User->getKey('pseudo') == $search[0]['Comment']['author']) {
            if ($this->request->is('post')) {

                $event = new CakeEvent('beforeDeleteComment', $this, ['comment_id' => $this->request->data['id'], 'news_id' => $search[0]['Comment']['news_id'], 'user' => $this->User->getAllFromCurrentUser()]);
                $this->getEventManager()->dispatch($event);
                if ($event->isStopped()) {
                    return $event->result;
                }

                $this->Comment->delete($this->request->data['id']);
                echo 'true';
            } else {
                echo 'NOT_POST';
            }
        } else {
            echo 'NOT_ADMIN';
        }
    }

    function admin_index()
    {
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {

            $this->set('title_for_layout', $this->Lang->get('NEWS__LIST_PUBLISHED'));
            $this->layout = 'admin';
            $this->loadModel('News');
            $view_news = $this->News->find('all', ['recursive' => 1]);
            $this->set(compact('view_news'));
        } else {
            $this->redirect('/');
        }
    }

    function admin_delete($id = false)
    {
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {
            if ($id != false) {

                $event = new CakeEvent('beforeDeleteNews', $this, ['news_id' => $id, 'user' => $this->User->getAllFromCurrentUser()]);
                $this->getEventManager()->dispatch($event);
                if ($event->isStopped()) {
                    return $event->result;
                }

                $this->loadModel('News');
                if ($this->News->delete($id)) {
                    $this->loadModel('Like');
                    $this->loadModel('Comment');
                    $this->Like->deleteAll(['Like.news_id' => $id]);
                    $this->Comment->deleteAll(['Comment.news_id' => $id]);
                    $this->History->set('DELETE_NEWS', 'news');
                    $this->Session->setFlash($this->Lang->get('NEWS__SUCCESS_DELETE'), 'default.success');
                    $this->redirect(['controller' => 'news', 'action' => 'index', 'admin' => true]);
                } else {
                    $this->redirect(['controller' => 'news', 'action' => 'index', 'admin' => true]);
                }
            } else {
                $this->redirect(['controller' => 'news', 'action' => 'index', 'admin' => true]);
            }
        } else {
            $this->redirect('/');
        }
    }

    function admin_add()
    {
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {
            $this->layout = 'admin';

            $this->set('title_for_layout', $this->Lang->get('NEWS__ADD_NEWS'));
        } else {
            $this->redirect('/');
        }
    }

    function admin_add_ajax()
    {
        $this->autoRender = false;
        $this->response->type('json');
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {

            if ($this->request->is('post')) {
                if (!empty($this->request->data['title']) and !empty($this->request->data['content']) and !empty($this->request->data['slug'])) {

                    $event = new CakeEvent('beforeAddNews', $this, ['news' => $this->request->data, 'user' => $this->User->getAllFromCurrentUser()]);
                    $this->getEventManager()->dispatch($event);
                    if ($event->isStopped()) {
                        return $event->result;
                    }

                    $this->loadModel('News');
                    $this->News->read(null, null);
                    $this->News->set([
                        'title' => $this->request->data['title'],
                        'content' => $this->request->data['content'],
                        'user_id' => $this->User->getKey('id'),
                        'updated' => date('Y-m-d H:i:s'),
                        'comments' => 0,
                        'likes' => 0,
                        'img' => 0,
                        'slug' => Inflector::slug($this->request->data['slug'], '-'),
                        'published' => $this->request->data['published']
                    ]);
                    $this->News->save();
                    $this->History->set('ADD_NEWS', 'news');
                    $this->response->body(json_encode(['statut' => true, 'msg' => $this->Lang->get('NEWS__SUCCESS_ADD')]));
                    $this->Session->setFlash($this->Lang->get('NEWS__SUCCESS_ADD'), 'default.success');
                } else {
                    $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')]));
                }
            } else {
                $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST')]));
            }
        } else {
            throw new ForbiddenException();
        }
    }

    function admin_edit($id = false)
    {
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {
            $this->layout = 'admin';

            if ($id != false) {
                $this->loadModel('News');
                $search = $this->News->find('all', ['conditions' => ['id' => $id]]);
                if (!empty($search)) {
                    $news = $search['0']['News'];
                    $this->set(compact('news'));
                } else {
                    throw new NotFoundException();
                }
            } else {
                throw new NotFoundException();
            }
        } else {
            $this->redirect('/');
        }
    }

    function admin_edit_ajax()
    {
        $this->autoRender = false;
        $this->response->type('json');
        if ($this->isConnected and $this->Permissions->can('MANAGE_NEWS')) {

            if ($this->request->is('post')) {

                if (!empty($this->request->data['title']) and !empty($this->request->data['content']) and !empty($this->request->data['id']) and !empty($this->request->data['slug'])) {

                    $event = new CakeEvent('beforeEditNews', $this, ['news' => $this->request->data, 'news_id' => $this->request->data['id'], 'user' => $this->User->getAllFromCurrentUser()]);
                    $this->getEventManager()->dispatch($event);
                    if ($event->isStopped()) {
                        return $event->result;
                    }

                    $this->loadModel('News');
                    $this->News->read(null, $this->request->data['id']);
                    $this->News->set([
                        'title' => $this->request->data['title'],
                        'content' => $this->request->data['content'],
                        'updated' => date('Y-m-d H:i:s'),
                        'slug' => Inflector::slug($this->request->data['slug'], '-'),
                        'published' => $this->request->data['published']
                    ]);
                    $this->News->save();
                    $this->History->set('EDIT_NEWS', 'news');
                    $this->response->body(json_encode(['statut' => true, 'msg' => $this->Lang->get('NEWS__SUCCESS_EDIT')]));
                    $this->Session->setFlash($this->Lang->get('NEWS__SUCCESS_EDIT'), 'default.success');
                } else {
                    $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')]));
                }
            } else {
                $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST')]));
            }
        } else {
            throw new ForbiddenException();
        }

    }

    /**
     * @return array|int
     */
    public function getNews()
    {
        $this->loadModel('News'); // on charge le model
        $search_news = $this->News->find('all', ['recursive' => 1, 'order' => 'id desc', 'conditions' => ['published' => 1]]); // on cherche les 3 dernières news (les plus veille)

        foreach ($search_news as $key => $model) {
            $search_news[$key]['News']['absolute_url'] = Router::url('/blog/' . $model['News']['slug'], true);
        }
        return $search_news;
    }

}
