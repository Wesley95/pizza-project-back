<?php

namespace App\Models\Repositories;

abstract class AbstractRepository
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Find one register
     *
     * @paran int $id
     * @return mixed
     */
    public function find(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Find one register or fail
     *
     * @paran int $id
     * @return array
     */
    public function findOrFail(int $id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Find first register
     *
     * @return mixed
     */
    public function first()
    {
        return $this->model->first();
    }

    /**
     * Get all registers
     *
     * @return array
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * Count all registers
     *
     * @return integer
     */
    public function count()
    {
        return $this->model->count();
    }

    /**
     * Gte with paginate
     *
     * @return array
     */
    public function paginate($limit = 10)
    {
        return $this->model->paginate($limit);
    }

    /**
     * Create new register
     *
     * @paran array $data
     * @return mixed
     */
    public function create(array $data)
    {
        if(!key_exists('nome', $data)) $data['nome'] = "";
        return $this->model->create($data);
    }

    /**
     * Update one register
     *
     * @paran int $id
     * @paran array $data
     * @return mixed
     */
    public function update(int $id, array $data)
    {
        return $this->findOrFail($id)->update($data);
    }

    /**
     * Delete one register
     *
     * @paran int $id
     * @return array
     */
    public function delete(int $id)
    {        
        return $this->findOrFail($id)->delete();
    }

    /**
     * Force delete one register
     *
     * @paran int $id
     * @return array
     */
    public function forceDelete(int $id)
    {
        return $this->findOrFail($id)->forceDelete();
    }
}
