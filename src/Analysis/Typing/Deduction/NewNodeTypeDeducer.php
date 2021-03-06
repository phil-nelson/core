<?php

namespace PhpIntegrator\Analysis\Typing\Deduction;

use UnexpectedValueException;

use PhpParser\Node;

/**
 * Type deducer that can deduce the type of a {@see Node\Expr\New_} node.
 */
class NewNodeTypeDeducer extends AbstractNodeTypeDeducer
{
    /**
     * @var NodeTypeDeducerInterface
     */
    protected $nodeTypeDeducer;

    /**
     * @param NodeTypeDeducerInterface $nodeTypeDeducer
     */
    public function __construct(NodeTypeDeducerInterface $nodeTypeDeducer)
    {
        $this->nodeTypeDeducer = $nodeTypeDeducer;
    }

    /**
     * @inheritDoc
     */
    public function deduce(Node $node, $file, $code, $offset)
    {
        if (!$node instanceof Node\Expr\New_) {
            throw new UnexpectedValueException("Can't handle node of type " . get_class($node));
        }

        return $this->deduceTypesFromNewNode($node, $file, $code, $offset);
    }

    /**
     * @param Node\Expr\New_ $node
     * @param string|null    $file
     * @param string         $code
     * @param int            $offset
     *
     * @return string[]
     */
    protected function deduceTypesFromNewNode(Node\Expr\New_ $node, $file, $code, $offset)
    {
        return $this->nodeTypeDeducer->deduce($node->class, $file, $code, $offset);
    }
}
