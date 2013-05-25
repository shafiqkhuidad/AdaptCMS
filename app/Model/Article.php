<?php

class Article extends AppModel
{
    /**
    * Name of our Model, table will look like 'adaptcms_articles'
    */
    public $name = 'Article';

    /**
    * Articles belong to a user (when added/edited) and a category
    */
    public $belongsTo = array(
        'User' => array(
            'className'    => 'User',
            'foreignKey'   => 'user_id'
        ),
        'Category' => array(
            'className' => 'Category',
            'foreignKey' => 'category_id'
        )
    );

    /**
    * Articles may have many article values and many comments related to it
    */
    public $hasMany = array(
        'ArticleValue' => array(
            'dependent' => true
        ),
        'Comment' => array(
            'dependent' => true
        )
    );

    /**
    * Articles must have a title
    */
    public $validate = array(
        'title' => array(
            'rule' => array(
                'notEmpty'
            )
        )
    );

    /**
    * A convenience function that will retrieve all related articles
    *
    * @param id of article
    * @param related json_encoded array of related articles
    * @return associative array of related articles
    */
    public function getRelatedArticles($id, $related)
    {
        $data = array();

        $find = $this->find('all', array(
            'conditions' => array(
                'OR' => array(
                    'Article.id' => json_decode($related),
                    'Article.related_articles LIKE' => '%"'.$id.'"%'
                )
            ),
            'contain' => array(
                'Category',
                'User',
                'ArticleValue' => array(
                    'Field',
                    'File'
                )
            )
        ));

        if (!empty($find))
        {
            foreach($find as $row)
            {
                $data[$row['Category']['slug']][] = $row;
            }
        }

        return array(
            'all' => $find,
            'category' => $data
        );
    }

    /**
    * Another convenience function, this time it calls the above getRelatedArticles function
    * and grabs comments.
    *
    * @param data to parse through
    * @return associative array
    */
    public function getAllRelatedArticles($data)
    {
        foreach($data as $key => $row)
        {
            if (!empty($row['Article']['related_articles']))
            {
                $data[$key]['RelatedArticles'] = $this->getRelatedArticles(
                    $row['Article']['id'], 
                    $row['Article']['related_articles']
                );
            }

            $data[$key]['Comments'] = $this->Comment->find('count', array(
                'conditions' => array(
                    'Comment.article_id' => $row['Article']['id'],
                    'Comment.active' => 1
                )
            ));
        }

        return $data;
    }

    /**
    * This works in conjuction with the Block feature. Doing a simple find with any conditions filled in by the user that
    * created the block. This is customizable so you can do a contain of related data if you wish.
    *
    * The function in this model will also match for a category filtering of articles and retrieve related articles/comments.
    *
    * @return associative array
    */
    public function getBlockData($data, $user_id)
    {
        $cond = array(
            'conditions' => array(
                'Article.deleted_time' => '0000-00-00 00:00:00',
                'Article.status !=' => 0,
                'Article.publish_time <=' => date('Y-m-d H:i:s')
            ),
            'contain' => array(
                'Category',
                'User'
            )
        );

        if (!empty($data['limit']))
        {
            $cond['limit'] = $data['limit'];
        }

        if (!empty($data['order_by']))
        {
            if ($data['order_by'] == "rand")
            {
                $data['order_by'] = 'RAND()';
            }

            $cond['order'] = 'Article.'.$data['order_by'].' '.$data['order_dir'];
        }

        if (!empty($data['data']))
        {
            $cond['conditions']['Article.id'] = $data['data'];
        }

        if (!empty($data['category_id']))
        {
            $cond['conditions']['Category.id'] = $data['category_id'];
        }

        return $this->getAllRelatedArticles($this->find('all', $cond));
    }

    /**
    * For block support, articles allow filtering by category. To enable this we call the view and pass a list of
    * categories to this element and get the resulting code, passing it back to blocks. It's not proper MVC, but
    * I don't know another way around it.
    *
    * @param data
    * @return string containing HTML to display
    */
    public function getBlockCustomOptions($data)
    {
        $view = new View();
        $categories = $this->Category->find('list');

        $data = $view->element('article_custom_options', array(
            'categories' => $categories, 
            'id' => (!empty($data['category_id']) ? $data['category_id'] : '') 
        ));

        return $data;
    }

    /**
    * Hooking up to the search feature, the params passed back will look for articles
    * based on the search param, include related data and pass back a permission that is required
    * to view the search result.
    *
    * @param q search term
    * @return array of search parameters
    */
    public function getSearchParams( $q )
    {
        return array(
            'conditions' => array(
                'OR' => array(
                    'Article.title LIKE' => '%' . $q . '%',
                    'ArticleValue.data LIKE' => '%' . $q . '%'
                )
            ),
            'contain' => array(
                'ArticleValue' => array(
                    'File',
                    'Field'
                ),
                'Category',
                'User'
            ),
            'joins' => array(
                array(
                    'table' => 'article_values',
                    'alias' => 'ArticleValue',
                    'type' => 'inner',
                    'conditions' => array(
                        'Article.id = ArticleValue.article_id'
                    )
                )
            ),
            'permissions' => array(
                'controller' => 'articles',
                'action' => 'view'
            ),
            'group' => 'Article.id'
        );
    }

    /**
    * This beforeSave will set the slug and ensure the proper File request data is being
    * passed to the behavior.
    *
    * @return true
    */
    public function beforeSave()
    {
        if (!empty($this->data['File']) && !empty($this->data['Files']))
        {
            $this->data['File'] = array_merge($this->data['File'], $this->data['Files']);
        } elseif (!empty($this->data['Files']))
        {
            $this->data['File'] = $this->data['Files'];
        }

        if (!empty($this->data['Article']['title']))
        {
            $this->data['Article']['slug'] = $this->slug($this->data['Article']['title']);
        }

        /**
        * Add
        */
        if (!empty($this->data['RelatedData']))
        {
            $this->data['Article']['related_articles'] = json_encode(
                $this->data['RelatedData']
            );
            unset($this->data['RelatedData']);
        }

        if (!empty($this->data['FieldData']))
        {
            foreach($this->data['FieldData'] as $key => $row)
            {
                $this->data['FieldData'][$key] = $this->slug($row);
            }
            
            $this->data['Article']['tags'] = 
                str_replace("'","",json_encode($this->data['FieldData']));
            unset($this->data['FieldData']);
        }

        if (!empty($this->data['Article']['settings']))
        {
            $this->data['Article']['settings'] = json_encode(
                $this->data['Article']['settings']
            );
        }
        
        if (!empty($this->data['Article']['publishing_date']))
        {
            $this->data['Article']['publish_time'] = 
                date("Y-m-d H:i:s", strtotime(
                    $this->data['Article']['publishing_date'] . ' ' .
                    $this->data['Article']['publishing_time']
            ));
            
            if ($this->data['Article']['publish_time'] == date("Y-m-d H:i:")."00" || 
                $this->data['Article']['publish_time'] <= date("Y-m-d H:i:")."00")
            {
                $this->data['Article']['publish_time'] = "0000-00-00 00:00:00";
            }
        }
        
        return true;
    }

    /**
    * The afterFind is primarily used to automatically decode json for Article data
    *
    * @param results
    * @return associative array of parsed results
    */
    public function afterFind($results)
    {
        if (empty($results))
        {
            return;
        }
        
        foreach($results as $key => $result)
        {
            if (!empty($result['ArticleValue']) && is_array($result['ArticleValue']))
            {
                foreach($result['ArticleValue'] as $value)
                {
                    if (!empty($value['Field']))
                    {
                        if (!empty($value['File']))
                        {
                            if (!empty($value['File']['filename']))
                            {
                                $results[$key]['Data'][$value['Field']['title']] = 
                                    $value['File']['dir'] . $value['File']['filename'];
                            }
                        }
                        else
                        {
                            $json = json_decode($value['data'], true);

                            if (empty($json) || !is_array($json))
                            {
                                $results[$key]['Data'][$value['Field']['title']] = $value['data'];
                            } else {
                                $results[$key]['Data'][$value['Field']['title']]['data'] = $json;
                                $results[$key]['Data'][$value['Field']['title']]['list'] = implode(', ', $json);
                            }
                        }
                    }
                }
            }

            if (!empty($result['Article']['tags']))
            {
                $results[$key]['Article']['tags'] = json_decode($result['Article']['tags'], true);
                $results[$key]['Article']['tags_list'] = implode(', ', $results[$key]['Article']['tags']);
            }

            if (!empty($result['Article']['settings']))
            {
                $results[$key]['Article']['settings'] = json_decode($result['Article']['settings'], true);
            }
        }

        return $results;
    }
}