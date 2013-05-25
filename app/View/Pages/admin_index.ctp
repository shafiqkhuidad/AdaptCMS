<?php $this->Html->addCrumb('Admin', '/admin') ?>
<?php $this->Html->addCrumb('Pages', null) ?>

<div class="left">
    <h1>Pages<?php if (!empty($this->params->named['trash'])): ?> - Trash<?php endif ?></h1>
</div>
<div class="btn-group" style="float:right;margin-bottom:10px">
  <a class="btn dropdown-toggle" data-toggle="dropdown">
    View <i class="icon-picture"></i>
    <span class="caret"></span>
  </a>
  <ul class="dropdown-menu view">
    <li>
        <?= $this->Html->link('<i class="icon-ok"></i> Active', array(
            'admin' => true, 
            'action' => 'index'
        ), array('escape' => false)) ?>
    </li>
    <li>
        <?= $this->Html->link('<i class="icon-trash"></i> Trash', array(
            'admin' => true, 
            'action' => 'index', 
            'trash' => 1
        ), array('escape' => false)) ?>
    </li>
  </ul>
</div>
<div class="clear"></div>

<?php if ($this->Admin->hasPermission($permissions['related']['pages']['admin_add'])): ?>
    <?= $this->Html->link('Add Page <i class="icon icon-plus icon-white"></i>', array('action' => 'add'), array(
        'class' => 'btn btn-info pull-right', 
        'style' => 'margin-bottom:10px',
        'escape' => false
    )) ?>
<?php endif ?>

<?php if (empty($this->request->data)): ?>
    <div class="clearfix"></div>
    <div class="well">
        No Items Found
    </div>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('title') ?></th>
                <th><?= $this->Paginator->sort('User.username', 'Author') ?></th>
                <th><?= $this->Paginator->sort('created') ?></th>
                <th></th>
            </tr>
        </thead>

        <?php foreach ($this->request->data as $data): ?>
            <tbody>
                <tr>
                    <td>
                        <?php if ($this->Admin->hasPermission($permissions['related']['pages']['display'], $data['User']['id']) && $this->Admin->isActive($data, 'Page')): ?>
                            <?= $this->Html->link($data['Page']['title'], array(
                                'admin' => false, 
                                'action' => 'display', 
                                $data['Page']['slug']
                            )) ?>
                        <?php else: ?>
                            <?= $data['Page']['title'] ?>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($this->Admin->hasPermission($permissions['related']['users']['profile'], $data['User']['id'])): ?>
                            <?= $this->Html->link($data['User']['username'], array(
                                'controller' => 'users',
                                'action' => 'profile',
                                $data['User']['username']
                            )) ?>
                        <?php endif ?>
                    </td>
                    <td>
                        <?= $this->Admin->time($data['Page']['created']) ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a class="btn btn-primary dropdown-toggle" data-toggle="dropdown" href="#">
                                Actions
                                <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($this->Admin->hasPermission($permissions['related']['pages']['display'], $data['User']['id']) && $this->Admin->isActive($data, 'Page')): ?>
                                    <li>
                                        <?= $this->Admin->view(
                                            $data['Page']['slug']
                                        ) ?>
                                    </li>
                                <?php endif ?>
                                
                                <?php if (empty($this->params->named['trash'])): ?>
                                    <?php if ($this->Admin->hasPermission($permissions['related']['pages']['admin_edit'], $data['User']['id'])): ?>
                                        <li>
                                            <?= $this->Admin->edit(
                                                $data['Page']['id']
                                            ) ?>
                                        </li>
                                    <?php endif ?>
                                    <?php if ($this->Admin->hasPermission($permissions['related']['pages']['admin_delete'], $data['User']['id'])): ?>
                                        <li>
                                            <?= $this->Admin->delete(
                                                $data['Page']['id'],
                                                $data['Page']['title'],
                                                'page'
                                            ) ?>
                                        </li>
                                    <?php endif ?>
                                <?php else: ?>
                                    <?php if ($this->Admin->hasPermission($permissions['related']['pages']['admin_restore'], $data['User']['id'])): ?>
                                        <li>
                                            <?= $this->Admin->restore(
                                                $data['Page']['id'],
                                                $data['Page']['title']
                                            ) ?>
                                        </li>
                                    <?php endif ?>
                                    <?php if ($this->Admin->hasPermission($permissions['related']['pages']['admin_delete'], $data['User']['id'])): ?>
                                        <li>
                                            <?= $this->Admin->delete_perm(
                                                $data['Page']['id'],
                                                $data['Page']['title'],
                                                'page'
                                            ) ?>
                                        </li>
                                    <?php endif ?>
                                <?php endif ?>
                            </ul>
                        </div>
                    </td>
                </tr>
            </tbody>
        <?php endforeach ?>
    </table>
<?php endif ?>

<?= $this->element('admin_pagination') ?>